import {readToken,saveToken,clearToken} from './device';
import type {User} from './types';
export const API_URL = 'https://nishaglobal.com/api/mobile.php';
let token: string|null = null;
export class ApiError extends Error { constructor(message:string, public status:number=0,public code='connection'){super(message)} }
export async function initialiseToken(){ token=await readToken(); }
export async function rememberToken(value:string){await saveToken(value);token=value}
export async function forgetToken(){token=null;await clearToken()}
export function hasToken(){return Boolean(token)}
export async function api<T=Record<string,unknown>>(action:string, input:Record<string,unknown>|FormData={},method:'GET'|'POST'='GET',signal?:AbortSignal):Promise<T>{
  const url=new URL(API_URL); url.searchParams.set('action',action);
  const headers:Record<string,string>={'Accept':'application/json'};
  if(token)headers.Authorization=`Bearer ${token}`;
  const timeout=action==='ai_review'?125000:30000;
  const controller=new AbortController();
  const timer=window.setTimeout(()=>controller.abort(),timeout);
  const options:RequestInit={method,headers,credentials:'omit',signal:signal||controller.signal};
  if(method==='GET'){
    if(input instanceof FormData)throw new ApiError('This upload requires a review request.');
    for(const [key,value] of Object.entries(input)){if(value!==undefined&&value!==null)url.searchParams.set(key,String(value));}
  } else if(input instanceof FormData)options.body=input;
  else {headers['Content-Type']='application/json';options.body=JSON.stringify(input)}
  try {
    let response:Response;
    try {response=await fetch(url,options)}catch{
      throw new ApiError(action==='ai_review'
        ? 'The connection was interrupted. Your response and review reference are retained. Check the pending review before starting another request.'
        : 'Your online library could not be reached. Your work is kept on this phone; try again when connected. Offline practice is ready below.');
    }
    let body:any;
    try{body=await response.json()}catch{throw new ApiError('The online service needs an update. You can keep using offline practice.',response.status,'service_unavailable')}
    if(!response.ok||body.success===false||body.error){
      if(response.status===401&&token){await forgetToken();window.dispatchEvent(new Event('nisha-session-expired'))}
      throw new ApiError(typeof body.error==='string'?body.error:'This request could not finish. Please try again.',response.status,body.code||'request_failed');
    }
    return body as T;
  }finally{window.clearTimeout(timer)}
}
export async function authenticate(mode:'login'|'register',input:Record<string,unknown>):Promise<User>{
  const reply=await api<{access_token:string;user:User}>(mode,{...input,device_name:'NISHA mobile'},'POST');
  if(!reply.access_token||!reply.user?.id)throw new ApiError('The account service needs an update. No sign-in was saved.');
  await rememberToken(reply.access_token);return reply.user;
}
