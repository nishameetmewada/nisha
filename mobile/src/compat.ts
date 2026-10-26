/** App data is JSON-only. Avoid depending on Safari 15.4 structuredClone. */
export function cloneJson<T>(value:T):T {
  return JSON.parse(JSON.stringify(value)) as T;
}

/** Secure UUID v4 references also work on iOS 15.0, before randomUUID. */
export function newReference():string {
  const bytes=crypto.getRandomValues(new Uint8Array(16));
  bytes[6]=(bytes[6]&0x0f)|0x40;
  bytes[8]=(bytes[8]&0x3f)|0x80;
  const hex=Array.from(bytes,value=>value.toString(16).padStart(2,'0'));
  return `${hex.slice(0,4).join('')}-${hex.slice(4,6).join('')}-${hex.slice(6,8).join('')}-${hex.slice(8,10).join('')}-${hex.slice(10).join('')}`;
}

/** The native dialog element is available from iOS 15.4; older devices use a focus-trapped overlay. */
export function openDialog(dialog:HTMLDialogElement):void {
  dialog.setAttribute('role','dialog');
  dialog.setAttribute('aria-modal','true');
  if(typeof dialog.showModal==='function'){dialog.showModal();return}
  const previous=document.activeElement instanceof HTMLElement?document.activeElement:null;
  const backdrop=document.createElement('div');backdrop.className='modal-backdrop';
  document.body.append(backdrop);backdrop.append(dialog);dialog.setAttribute('open','');
  const focusable=()=>[...dialog.querySelectorAll<HTMLElement>('button,input,select,textarea,a[href],[tabindex]')].filter(node=>!node.hasAttribute('disabled')&&node.tabIndex>=0);
  const keydown=(event:KeyboardEvent)=>{
    if(event.key==='Escape'){event.preventDefault();dialog.close();return}
    if(event.key!=='Tab')return;
    const targets=focusable();
    if(!targets.length){event.preventDefault();return}
    const first=targets[0],last=targets[targets.length-1];
    if(event.shiftKey&&(document.activeElement===first||!dialog.contains(document.activeElement))){event.preventDefault();last.focus()}
    else if(!event.shiftKey&&(document.activeElement===last||!dialog.contains(document.activeElement))){event.preventDefault();first.focus()}
  };
  dialog.close=()=>{if(!dialog.hasAttribute('open'))return;dialog.removeAttribute('open');dialog.dispatchEvent(new Event('close'))};
  document.addEventListener('keydown',keydown);
  dialog.addEventListener('close',()=>{document.removeEventListener('keydown',keydown);backdrop.remove();previous?.focus()},{once:true});
  (focusable()[0]||dialog).focus();
}
