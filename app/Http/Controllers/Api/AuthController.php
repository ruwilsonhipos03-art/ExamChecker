<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\ExamSchedule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    private const TOKEN_TTL_MINUTES = 10;

    private function generateSixDigitCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function storeEmailToken(string $table, string $email, string $code): void
    {
        DB::table($table)->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );
    }

    private function validateEmailToken(string $table, string $email, string $code): bool
    {
        $record = DB::table($table)->where('email', $email)->first();
        if (!$record) {
            return false;
        }

        $createdAt = $record->created_at ? \Carbon\Carbon::parse($record->created_at) : null;
        if (!$createdAt || $createdAt->diffInMinutes(now()) > self::TOKEN_TTL_MINUTES) {
            return false;
        }

        return Hash::check($code, $record->token);
    }
    /**
     * Handle user login and token generation
     */
    public function login(Request $request)
    {
        // 1. Validate the request
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        // 2. Attempt to authenticate
        if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']])) {
            // ... inside the if (Auth::attempt(...)) block
            /** @var \App\Models\User $user */ // This line tells VS Code that $user has Sanctum traits
            $user = Auth::user();

            // 3. Create a Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;

            // 4. Return comprehensive user data to avoid 'undefined' errors in Vue
            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'middle_initial' => $user->middle_initial,
                    'last_name' => $user->last_name,
                    'extension_name' => $user->extension_name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'employee_id' => $user->employee_id, // Useful for linking to faculty records
                ]
            ]);
        }

        // 5. Return error if credentials fail
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid username or password'
        ], 401);
    }

    /**
     * Revoke the user's token (Logout)
     */
    public function logout(Request $request)
    {
        // Delete the token that was used for this request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Optional: Get current authenticated user profile
     */
    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function register(Request $request)
    {
        // 1. Validation
        $validated = $request->validate([
            'first_name'     => 'required|string|max:255',
            'middle_name'    => 'nullable|string|max:2',
            'last_name'      => 'required|string|max:255',
            'extension_name' => 'nullable|string|max:10',
            'email'          => 'required|string|email|max:255|unique:users',
            'username'       => 'required|string|max:255|unique:users',
            'password'       => 'required|string|min:8|confirmed',
        ]);

        try {
            // 2. Database Transaction with Concurrency Control
            return DB::transaction(function () use ($validated) {

                // Find earliest schedule with capacity.
                // lockForUpdate() prevents race conditions.
                $availableSchedule = ExamSchedule::where('date', '>=', now())
                    ->whereRaw('current_examinees < capacity')
                    ->orderBy('date', 'asc')
                    ->lockForUpdate()
                    ->first();

                if (!$availableSchedule) {
                    throw new \Exception("We are sorry! All exam slots are currently full. Please contact the Admissions Office.");
                }

                // 3. Create the Student User
                $user = User::create([
                    'first_name'       => $validated['first_name'],
                    'middle_initial'   => $validated['middle_name'],
                    'last_name'        => $validated['last_name'],
                    'extension_name'   => $validated['extension_name'],
                    'email'            => $validated['email'],
                    'username'         => $validated['username'],
                    'password'         => Hash::make($validated['password']),
                    'role'             => 'student',
                    'exam_schedule_id' => $availableSchedule->id, // Link to schedule
                ]);

                // 4. Update the Examinee Counter
                $availableSchedule->increment('current_examinees');

                // 5. Generate Access Token
                $token = $user->createToken('auth_token')->plainTextToken;

                return response()->json([
                    'status'   => 'success',
                    'token'    => $token,
                    'user'     => $user,
                    'schedule' => [
                        'date'   => \Carbon\Carbon::parse($availableSchedule->date)->format('F j, Y'),
                        'time'   => $availableSchedule->time,
                        'location'  => $availableSchedule->location,
                    ]
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function sendEmailVerificationCode(Request $request)
    {
        $user = $request->user();
        $code = $this->generateSixDigitCode();

        $this->storeEmailToken('email_verification_tokens', $user->email, $code);

        Mail::raw("Your verification code is: {$code}\nThis code expires in " . self::TOKEN_TTL_MINUTES . " minutes.", function ($message) use ($user) {
            $message->to($user->email)->subject('Email Verification Code');
        });

        return response()->json(['status' => 'success', 'message' => 'Verification code sent.']);
    }

    public function verifyEmailCode(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        if (!$this->validateEmailToken('email_verification_tokens', $user->email, $validated['code'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired code.'], 422);
        }

        $user->update(['email_verified_at' => now()]);
        DB::table('email_verification_tokens')->where('email', $user->email)->delete();

        return response()->json(['status' => 'success', 'message' => 'Email verified successfully.']);
    }

    public function sendForgotPasswordCode(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $code = $this->generateSixDigitCode();
        $this->storeEmailToken('password_reset_tokens', $validated['email'], $code);

        Mail::raw("Your password reset code is: {$code}\nThis code expires in " . self::TOKEN_TTL_MINUTES . " minutes.", function ($message) use ($validated) {
            $message->to($validated['email'])->subject('Password Reset Code');
        });

        return response()->json(['status' => 'success', 'message' => 'Reset code sent.']);
    }

    public function resetPasswordWithCode(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!$this->validateEmailToken('password_reset_tokens', $validated['email'], $validated['code'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired code.'], 422);
        }

        $user = User::where('email', $validated['email'])->firstOrFail();
        $user->update(['password' => Hash::make($validated['password'])]);
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return response()->json(['status' => 'success', 'message' => 'Password reset successfully.']);
    }
}
