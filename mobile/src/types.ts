export type Skill = 'reading'|'listening'|'writing'|'speaking';
export type Answer = string|string[]|Record<string,string>;
export type Answers = Record<string, Answer>;
export interface Option {id:string;text:string}
export interface Item { id:string;type:string;task_type?:string;title?:string;prompt?:string;instructions?:string;resource_id?:string;secondary_resource_id?:string;options?:Option[];select_count?:number;text?:string;blanks?:{id:string;options?:string[];answer?:string[]}[];min_words?:number;max_words?:number;suggested_seconds?:number;prep_seconds?:number;response_seconds?:number;cues?:string[];rubric?:string[];answer?:unknown;explanation?:string;[key:string]:unknown }
export interface Resource {kind:string;title?:string;paragraphs?:string[];audio_script?:string;description?:string;chart?:{unit?:string;categories?:string[];series?:{name:string;values:number[]}[];[key:string]:unknown};columns?:string[];rows?:unknown[][];[key:string]:unknown}
export interface Section {id:string;title:string;duration_seconds:number;navigation:string;instructions?:string;items:Item[];item_count?:number}
export interface Exam {id:string;name:string;exam:string;version:string;description?:string;format_note?:string;course_id?:string;session_type?:string;timing_mode?:string;generated?:boolean;content_source?:string;teacher_reviewed?:boolean;resources:Record<string,Resource>;sections:Section[];requires_access?:boolean;item_count?:number;[key:string]:unknown}
export interface Review {id:string;type:string;prompt?:string;title?:string;response:Answer;correct?:unknown;score:number|null;max_score?:number|null;maximum?:number|null;explanation?:string;rubric?:string[];section_id?:string;resource_id?:string;[key:string]:unknown}
export interface Result {exam_id:string;name:string;exam:string;exam_version?:string;attempt_id?:string;objective_score:number;objective_total:number;answered_items?:number;total_items?:number;review:Review[];responses?:Answers;resources?:Record<string,Resource>;session_type?:string;sections?:unknown[];content_source?:string;format_note?:string;[key:string]:unknown}
export interface Envelope {success?:boolean;saved?:boolean;result_id?:number|null;save_message?:string;result:Result;[key:string]:unknown}
export interface Course {id:string;name:string;exam:string;description?:string;full_exam_id:string;modules:{id:string;title:string;skill:string;item_count:number;practice_id:string}[];sections:{id:string;title:string;exam_id:string;item_count:number;duration_seconds:number}[]}
export interface User {id:number;name:string;email:string;created_at?:string}
export interface Access {paid_active:boolean;paid_until?:string;trial_state?:string;trial_exam_id?:string;trial_attempt_id?:string}
export interface Card {id:string;name:string;course_id?:string;skill?:string;session_type?:string;task_type?:string;item_count?:number;duration_seconds?:number;requires_access?:boolean}
export interface Attempt {exam:Exam;id:string;answers:Answers;sectionIndex:number;itemIndex:number;startedAt:number;sectionDeadline:number;completed:string[];offline:boolean;assisted:boolean;scope:string;expiresAt:number;itemDeadlines:Record<string,{prep:number;end:number}>;aiDrafts?:Record<string,string>}
export type ReviewItem = Review;
export type ResultEnvelope = Envelope;
export interface Submission {exam_id:string;exam_version:string;attempt_id:string;answers:Answers;elapsed_seconds:number;completed_sections:string[];assisted:boolean}
