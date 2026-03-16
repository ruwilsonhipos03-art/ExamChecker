import{z as N,c as r,a as e,e as f,s as g,l as k,d as v,v as R,C as h,F as _,q as y,m as L,o as n,t as c}from"./app-ChfA8GCm.js";const O={class:"container-fluid py-4"},U={class:"card shadow-sm border-0 mb-4"},C={class:"card-body p-4"},F={class:"d-flex justify-content-between align-items-center flex-wrap gap-2"},j=["disabled"],B={class:"card shadow-sm border-0 mb-4"},P={class:"card-body bg-light border-bottom p-3"},V={class:"row g-3 align-items-end"},$={class:"col-md-4"},D={class:"col-md-3"},q=["value"],M={class:"col-md-3"},T={class:"card-body p-0"},z={class:"table-responsive"},E={class:"table table-hover mb-0 align-middle"},W={key:0},H={key:1},G={class:"ps-3"},I={class:"fw-semibold"},Q={__name:"Students",setup(J){const i=f(!1),p=f([]),o=f({search:"",programName:"",sortOrder:"asc"}),x=g(()=>[...new Set(p.value.map(s=>s.program_name).filter(Boolean))].sort((s,t)=>String(s).localeCompare(String(t)))),m=g(()=>{let s=[...p.value];if(o.value.search){const l=o.value.search.toLowerCase();s=s.filter(a=>[a.student_number,a.full_name,a.username,a.email,a.program_name].map(u=>String(u||"").toLowerCase()).join(" ").includes(l))}o.value.programName&&(s=s.filter(l=>l.program_name===o.value.programName));const t=o.value.sortOrder==="asc"?1:-1;return s.sort((l,a)=>String(l.full_name||"").localeCompare(String(a.full_name||""))*t),s}),w=async()=>{i.value=!0;try{const{data:s}=await L.get("/api/admin/students");p.value=Array.isArray(s?.data)?s.data:[]}catch(s){p.value=[],window.Swal?.fire({icon:"error",title:"Failed to load students",text:s?.response?.data?.message||"Please refresh and try again."})}finally{i.value=!1}},b=s=>String(s??"").replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;").replaceAll('"',"&quot;").replaceAll("'","&#039;"),S=()=>{if(i.value||m.value.length===0)return;const t=`
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
                        </tr>
                    </thead>
                    <tbody>${m.value.map((u,A)=>`
        <tr>
            <td>${A+1}</td>
            <td>${b(u.student_number||"-")}</td>
            <td>${b(u.full_name||"-")}</td>
            <td>${b(u.program_name||"N/A")}</td>
        </tr>
    `).join("")}</tbody>
                </table>
            </body>
        </html>
    `,l=new Blob(["\uFEFF",t],{type:"application/msword"}),a=URL.createObjectURL(l),d=document.createElement("a");d.href=a,d.download="Admin_Students_Report.doc",document.body.appendChild(d),d.click(),d.remove(),URL.revokeObjectURL(a)};return N(w),(s,t)=>(n(),r("div",O,[e("div",U,[e("div",C,[e("div",F,[t[4]||(t[4]=e("div",null,[e("h4",{class:"fw-bold mb-1 text-dark"},"Students"),e("p",{class:"text-muted small mb-0"},"All registered students")],-1)),e("button",{class:"btn btn-success fw-bold px-4",disabled:i.value||m.value.length===0,onClick:S},[...t[3]||(t[3]=[e("i",{class:"bi bi-download me-2"},null,-1),k("Download Word ",-1)])],8,j)])])]),e("div",B,[e("div",P,[e("div",V,[e("div",$,[t[5]||(t[5]=e("label",{class:"form-label small fw-semibold mb-1"},"Search",-1)),v(e("input",{"onUpdate:modelValue":t[0]||(t[0]=l=>o.value.search=l),type:"text",class:"form-control form-control-sm",placeholder:"Student #, name, program..."},null,512),[[R,o.value.search,void 0,{trim:!0}]])]),e("div",D,[t[7]||(t[7]=e("label",{class:"form-label small fw-semibold mb-1"},"Program",-1)),v(e("select",{"onUpdate:modelValue":t[1]||(t[1]=l=>o.value.programName=l),class:"form-select form-select-sm"},[t[6]||(t[6]=e("option",{value:""},"All Programs",-1)),(n(!0),r(_,null,y(x.value,l=>(n(),r("option",{key:l,value:l},c(l),9,q))),128))],512),[[h,o.value.programName]])]),e("div",M,[t[9]||(t[9]=e("label",{class:"form-label small fw-semibold mb-1"},"Sort Order",-1)),v(e("select",{"onUpdate:modelValue":t[2]||(t[2]=l=>o.value.sortOrder=l),class:"form-select form-select-sm"},[...t[8]||(t[8]=[e("option",{value:"asc"},"Ascending",-1),e("option",{value:"desc"},"Descending",-1)])],512),[[h,o.value.sortOrder]])])])]),e("div",T,[e("div",z,[e("table",E,[t[12]||(t[12]=e("thead",{class:"table-light"},[e("tr",null,[e("th",{class:"ps-3"},"No."),e("th",null,"Student #"),e("th",null,"Full Name"),e("th",null,"Program")])],-1)),e("tbody",null,[i.value?(n(),r("tr",W,[...t[10]||(t[10]=[e("td",{colspan:"4",class:"text-center py-4 text-muted"},"Loading students...",-1)])])):m.value.length===0?(n(),r("tr",H,[...t[11]||(t[11]=[e("td",{colspan:"4",class:"text-center py-4 text-muted"},"No students found.",-1)])])):(n(!0),r(_,{key:2},y(m.value,(l,a)=>(n(),r("tr",{key:l.id},[e("td",G,c(a+1),1),e("td",I,c(l.student_number||"-"),1),e("td",null,c(l.full_name||"-"),1),e("td",null,c(l.program_name||"N/A"),1)]))),128))])])])])])]))}};export{Q as default};
