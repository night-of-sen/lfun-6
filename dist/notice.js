'use strict';
const notice=document.getElementById('notice');document.getElementById('noticeOpen').addEventListener('click',()=>notice.showModal());document.getElementById('noticeFooter').addEventListener('click',()=>notice.showModal());document.getElementById('noticeClose').addEventListener('click',()=>notice.close());notice.addEventListener('click',e=>{if(e.target===notice)notice.close()});
