import{z as k,c as n,a as e,e as v,s as h,l as R,d as f,v as O,C as _,F as g,q as x,m as U,o as d,t as r}from"./app-CVeKgjkC.js";const C={class:"container-fluid py-4"},L={class:"card shadow-sm border-0 mb-4"},$={class:"card-body p-4"},E={class:"d-flex justify-content-between align-items-center flex-wrap gap-2"},F=["disabled"],V={class:"card shadow-sm border-0 mb-4"},j={class:"card-body bg-light border-bottom p-3"},B={class:"row g-3 align-items-end"},P={class:"col-md-4"},D={class:"col-md-3"},q=["value"],z={class:"col-md-3"},M=["value"],T={class:"col-md-2"},W={class:"card-body p-0"},H={class:"table-responsive"},G={class:"table table-hover mb-0 align-middle"},I={key:0},J={key:1},K={class:"ps-3"},Q={class:"fw-semibold"},X={class:"text-end"},Y={class:"text-capitalize"},te={__name:"Students",setup(Z){const u=v(!1),c=v([]),a=v({search:"",programName:"",examName:"",sortOrder:"asc"}),y=h(()=>[...new Set(c.value.map(s=>s.program_name).filter(Boolean))].sort((s,t)=>String(s).localeCompare(String(t)))),S=h(()=>[...new Set(c.value.map(s=>s.exam_name).filter(s=>s&&s!=="N/A"))].sort((s,t)=>String(s).localeCompare(String(t)))),p=h(()=>{let s=[...c.value];if(a.value.search){const l=a.value.search.toLowerCase();s=s.filter(o=>[o.student_number,o.full_name,o.username,o.email,o.program_name,o.exam_name].map(m=>String(m||"").toLowerCase()).join(" ").includes(l))}a.value.programName&&(s=s.filter(l=>l.program_name===a.value.programName)),a.value.examName&&(s=s.filter(l=>l.exam_name===a.value.examName));const t=a.value.sortOrder==="asc"?1:-1;return s.sort((l,o)=>String(l.full_name||"").localeCompare(String(o.full_name||""))*t),s}),N=async()=>{u.value=!0;try{const{data:s}=await U.get("/api/admin/students");c.value=Array.isArray(s?.data)?s.data:[]}catch(s){c.value=[],window.Swal?.fire({icon:"error",title:"Failed to load students",text:s?.response?.data?.message||"Please refresh and try again."})}finally{u.value=!1}},b=s=>String(s??"").replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;").replaceAll('"',"&quot;").replaceAll("'","&#039;"),A=()=>{if(u.value||p.value.length===0)return;const t=`
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
            <head>
                <meta charset="UTF-8" />
                <title>Admin Students Report</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    h2 { margin: 0 0 12px; text-align: center; }
                    table { border-collapse: collapse; width: 100%; font-size: 13px; }
                    th, td { border: 1px solid #cbd5e1; padding: 7px 8px; }
                    th { background: #e2e8f0; text-align: left; }
                </style>
            </head>
            <body>
                <h2>Admin Students Report</h2>
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Student #</th>
                            <th>Full Name</th>
                            <th>Program</th>
                            <th>Exam</th>
                            <th>Score</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>${p.value.map((m,w)=>`
        <tr>
            <td>${w+1}</td>
            <td>${b(m.student_number||"-")}</td>
            <td>${b(m.full_name||"-")}</td>
            <td>${b(m.program_name||"N/A")}</td>
            <td>${b(m.exam_name||"N/A")}</td>
            <td>${m.exam_total_score??"-"}</td>
            <td>${b(m.exam_status||"not_taken")}</td>
        </tr>
    `).join("")}</tbody>
                </table>
            </body>
        </html>
    `,l=new Blob(["\uFEFF",t],{type:"application/msword"}),o=URL.createObjectURL(l),i=document.createElement("a");i.href=o,i.download="Admin_Students_Report.doc",document.body.appendChild(i),i.click(),i.remove(),URL.revokeObjectURL(o)};return k(N),(s,t)=>(d(),n("div",C,[e("div",L,[e("div",$,[e("div",E,[t[5]||(t[5]=e("div",null,[e("h4",{class:"fw-bold mb-1 text-dark"},"Students"),e("p",{class:"text-muted small mb-0"},"All registered students with their latest exam record")],-1)),e("button",{class:"btn btn-success fw-bold px-4",disabled:u.value||p.value.length===0,onClick:A},[...t[4]||(t[4]=[e("i",{class:"bi bi-download me-2"},null,-1),R("Download Word ",-1)])],8,F)])])]),e("div",V,[e("div",j,[e("div",B,[e("div",P,[t[6]||(t[6]=e("label",{class:"form-label small fw-semibold mb-1"},"Search",-1)),f(e("input",{"onUpdate:modelValue":t[0]||(t[0]=l=>a.value.search=l),type:"text",class:"form-control form-control-sm",placeholder:"Student #, name, username, email..."},null,512),[[O,a.value.search,void 0,{trim:!0}]])]),e("div",D,[t[8]||(t[8]=e("label",{class:"form-label small fw-semibold mb-1"},"Program",-1)),f(e("select",{"onUpdate:modelValue":t[1]||(t[1]=l=>a.value.programName=l),class:"form-select form-select-sm"},[t[7]||(t[7]=e("option",{value:""},"All Programs",-1)),(d(!0),n(g,null,x(y.value,l=>(d(),n("option",{key:l,value:l},r(l),9,q))),128))],512),[[_,a.value.programName]])]),e("div",z,[t[10]||(t[10]=e("label",{class:"form-label small fw-semibold mb-1"},"Exam",-1)),f(e("select",{"onUpdate:modelValue":t[2]||(t[2]=l=>a.value.examName=l),class:"form-select form-select-sm"},[t[9]||(t[9]=e("option",{value:""},"All Exams",-1)),(d(!0),n(g,null,x(S.value,l=>(d(),n("option",{key:l,value:l},r(l),9,M))),128))],512),[[_,a.value.examName]])]),e("div",T,[t[12]||(t[12]=e("label",{class:"form-label small fw-semibold mb-1"},"Sort Order",-1)),f(e("select",{"onUpdate:modelValue":t[3]||(t[3]=l=>a.value.sortOrder=l),class:"form-select form-select-sm"},[...t[11]||(t[11]=[e("option",{value:"asc"},"Ascending",-1),e("option",{value:"desc"},"Descending",-1)])],512),[[_,a.value.sortOrder]])])])]),e("div",W,[e("div",H,[e("table",G,[t[15]||(t[15]=e("thead",{class:"table-light"},[e("tr",null,[e("th",{class:"ps-3"},"No."),e("th",null,"Student #"),e("th",null,"Full Name"),e("th",null,"Program"),e("th",null,"Exam"),e("th",{class:"text-end"},"Score"),e("th",null,"Status")])],-1)),e("tbody",null,[u.value?(d(),n("tr",I,[...t[13]||(t[13]=[e("td",{colspan:"7",class:"text-center py-4 text-muted"},"Loading students...",-1)])])):p.value.length===0?(d(),n("tr",J,[...t[14]||(t[14]=[e("td",{colspan:"7",class:"text-center py-4 text-muted"},"No students found.",-1)])])):(d(!0),n(g,{key:2},x(p.value,(l,o)=>(d(),n("tr",{key:l.id},[e("td",K,r(o+1),1),e("td",Q,r(l.student_number||"-"),1),e("td",null,r(l.full_name||"-"),1),e("td",null,r(l.program_name||"N/A"),1),e("td",null,r(l.exam_name||"N/A"),1),e("td",X,r(l.exam_total_score??"-"),1),e("td",Y,r(l.exam_status||"not_taken"),1)]))),128))])])])])])]))}};export{te as default};
