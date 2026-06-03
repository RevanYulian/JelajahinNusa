<?php /* Shared CSS for all artikel detail pages */ ?>
<style>
:root{--dark-bg:#000;--dark-bg-secondary:#1A1A1A;--text-primary:#FFF;--text-secondary:#AAA;--accent-green:#1F4529;}
*{margin:0;padding:0;box-sizing:border-box;}html{scroll-behavior:smooth;}
body{font-family:'Poppins',Arial,sans-serif;background:var(--dark-bg);color:var(--text-primary);line-height:1.6;overflow-x:hidden;zoom:90%;}
.container{max-width:1200px;margin:0 auto;padding:0 30px;}
a{text-decoration:none;color:inherit;}ul{list-style:none;}img{max-width:100%;display:block;}
.btn{display:inline-block;padding:12px 28px;border-radius:30px;font-weight:500;font-size:16px;transition:.3s;border:none;cursor:pointer;}
.btn-primary{background:var(--text-primary);color:#000;}.btn-primary:hover{opacity:.9;}
/* Header — identik dengan index.php */
header{position:sticky;top:0;z-index:9000;background:rgba(18,18,18,.85);backdrop-filter:blur(10px);overflow:visible;}
.navbar{display:flex;justify-content:space-between;align-items:center;padding:0;}
.logo img{height:100px;width:auto;}
.nav-menu ul{display:flex;gap:45px;}
.nav-menu li a{color:var(--text-secondary);font-weight:500;transition:.3s;}
.nav-menu li a:hover,.nav-menu li a.active{color:#fff;}
.header-right{display:flex;align-items:center;gap:20px;}
.search-bar{display:flex;align-items:center;border:1px solid var(--text-secondary);border-radius:30px;padding:8px 15px;}
.search-bar input{background:transparent;border:none;outline:none;color:#fff;margin-left:10px;font-family:'Poppins',sans-serif;width:160px;}
.user-profile{display:flex;align-items:center;gap:8px;cursor:pointer;position:relative;}
.user-profile i{font-size:28px;}
.user-profile span{font-size:14px;color:var(--text-secondary);}
.user-dropdown{display:none;position:absolute;top:calc(100% + 10px);right:0;background:#1A1A1A;border:1px solid #333;border-radius:10px;min-width:210px;z-index:9999;padding:8px 0;box-shadow:0 8px 30px rgba(0,0,0,.6);}
.user-dropdown a{display:block;padding:10px 18px;color:var(--text-secondary);font-size:14px;transition:.2s;}
.user-dropdown a:hover{color:#fff;background:rgba(255,255,255,.05);}
.user-dropdown.open{display:block;}
.menu-toggle-nav{display:none;font-size:24px;cursor:pointer;color:#fff;}
/* Hero */
.post-hero{height:50vh;position:relative;display:flex;align-items:flex-end;overflow:hidden;z-index:1;isolation:isolate;}
.hero-bg{position:absolute;inset:0;background-size:cover;background-position:center;filter:brightness(.55);}
.hero-title{position:relative;z-index:2;padding:40px 0;}
.hero-title h2{font-size:40px;font-weight:700;line-height:1.2;text-shadow:0 2px 10px rgba(0,0,0,.5);}
/* Content layout */
.post-body{padding:60px 0;}
.content-layout{display:grid;grid-template-columns:1fr 340px;gap:50px;align-items:start;}
@media(max-width:900px){.content-layout{grid-template-columns:1fr;}}
.main-content p{color:rgba(255,255,255,.85);font-size:16px;line-height:1.85;margin-bottom:22px;}
.main-content h3{font-size:22px;font-weight:600;margin:28px 0 14px;}
.main-content ul{margin-left:20px;list-style:disc;margin-bottom:20px;}
.main-content li{color:rgba(255,255,255,.82);font-size:15px;line-height:1.8;margin-bottom:10px;}
.image-caption{border-radius:12px;overflow:hidden;margin:20px 0;}
.image-caption img{width:100%;max-height:420px;object-fit:cover;}
/* Sidebar */
.sidebar{display:flex;flex-direction:column;gap:24px;position:sticky;top:90px;}
.sidebar-box{background:var(--dark-bg-secondary);border-radius:14px;padding:22px;}
.sidebar-box h4{font-size:16px;font-weight:600;margin-bottom:14px;}
.map-placeholder iframe{width:100%;height:220px;border-radius:8px;display:block;}
.promo-box{text-align:center;}
.promo-box img{max-width:80px;margin:0 auto 10px;border-radius:10px;}
.app-links{display:flex;flex-direction:column;gap:10px;margin-top:16px;}
.app-links img{height:38px;width:auto;margin:0 auto;}
/* Gallery */
.related-gallery{padding:40px 0 60px;}
.related-gallery h3{font-size:22px;font-weight:600;margin-bottom:24px;}
.gallery-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;}
@media(max-width:768px){.gallery-grid{grid-template-columns:repeat(2,1fr);}}
.gallery-card{border-radius:10px;overflow:hidden;position:relative;}
.gallery-card img{width:100%;height:180px;object-fit:cover;transition:transform .4s;}
.gallery-card:hover img{transform:scale(1.06);}
.gallery-caption{position:absolute;bottom:0;left:0;right:0;padding:8px 12px;background:rgba(0,0,0,.65);font-size:12px;}
/* Info box */
.info-box{background:rgba(31,69,41,.2);border:1px solid rgba(31,69,41,.5);border-radius:12px;padding:20px 24px;margin:24px 0;}
.info-box h4{font-size:15px;font-weight:600;color:#6fcf97;margin-bottom:10px;}
.info-box ul{margin-left:18px;list-style:disc;}
.info-box li{font-size:14px;color:rgba(255,255,255,.8);margin-bottom:6px;}
/* Footer */
footer{background:var(--dark-bg-secondary);padding:60px 0 28px;}
.footer-subscribe{text-align:center;padding-bottom:44px;border-bottom:1px solid #333;margin-bottom:44px;}
.footer-subscribe h3{font-size:24px;font-weight:600;margin-bottom:8px;}
.footer-subscribe p{color:var(--text-secondary);margin-bottom:20px;}
.subscribe-form{display:flex;justify-content:center;gap:12px;}
.subscribe-form input{background:rgba(255,255,255,.08);border:1px solid #444;border-radius:30px;padding:12px 22px;color:#fff;font-family:'Poppins',sans-serif;width:340px;outline:none;}
.footer-main{display:grid;grid-template-columns:repeat(4,1fr);gap:36px;margin-bottom:36px;}
.footer-col h4{font-size:15px;font-weight:600;margin-bottom:14px;}
.footer-col p,.footer-col li,.footer-col span{color:var(--text-secondary);font-size:13px;line-height:1.9;}
.footer-col a:hover{color:#fff;}
.social-icons{display:flex;gap:12px;}
.social-icons a{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;transition:.3s;}
.social-icons a:hover{background:var(--accent-green);color:#fff;}
.footer-bottom{text-align:center;padding-top:22px;border-top:1px solid #333;color:var(--text-secondary);font-size:13px;}
.toast{position:fixed;bottom:28px;right:28px;background:#1F4529;color:#fff;padding:13px 22px;border-radius:10px;font-size:14px;z-index:99999;opacity:0;transform:translateY(16px);transition:.3s;pointer-events:none;}
.toast.show{opacity:1;transform:translateY(0);}
/* Responsive */
@media(max-width:768px){.nav-menu{display:none;position:absolute;top:100%;left:0;width:100%;background:rgba(18,18,18,.98);padding:20px;}.nav-menu.active{display:block;}.nav-menu ul{flex-direction:column;gap:16px;}.menu-toggle-nav{display:block;}.hero-title h2{font-size:26px;}.footer-main{grid-template-columns:1fr 1fr;}}
</style>
<script>
function initPage(){
  // Mobile nav
  document.getElementById('menuToggle').addEventListener('click',()=>{
    const m=document.getElementById('navMenu');m.classList.toggle('active');
    const ic=document.querySelector('#menuToggle i');ic.classList.toggle('fa-bars');ic.classList.toggle('fa-times');
  });
  // User dropdown
  const up=document.getElementById('upBtn');
  if(up){
    up.addEventListener('click',e=>{e.stopPropagation();document.getElementById('userDD').classList.toggle('open');});
    document.addEventListener('click',()=>{const d=document.getElementById('userDD');if(d)d.classList.remove('open');});
  }
  // Toast auto-hide
  const t=document.querySelector('.toast.show');if(t)setTimeout(()=>t.classList.remove('show'),3500);
}
document.addEventListener('DOMContentLoaded',initPage);
</script>