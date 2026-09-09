from pathlib import Path
import shutil, zipfile, textwrap

OUT=Path('build/d2d-tool1-eligibility-portal-v3')
ZIP=Path('build/d2d-tool1-eligibility-portal-v3.zip')
if OUT.exists(): shutil.rmtree(OUT)
OUT.mkdir(parents=True)

pub=OUT/'public-app'; portal=OUT/'portal-app'
for d in [
 pub/'payload/app/Providers', pub/'payload/app/Http/Controllers/Tools', pub/'payload/app/Services/Tools', pub/'payload/resources/views/tools', pub/'payload/public/d2d-tools', pub/'payload/routes',
 portal/'payload/app/Providers', portal/'payload/app/Http/Controllers', portal/'payload/app/Services', portal/'payload/resources/views/d2d-saved-results', portal/'payload/public', portal/'payload/routes']:
    d.mkdir(parents=True,exist_ok=True)

def w(path,s): path.write_text(textwrap.dedent(s).lstrip())

# ---------- shared catalogue ----------
w(pub/'payload/app/Services/Tools/D2dScholarshipCatalogue.php', r'''<?php
namespace App\Services\Tools;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class D2dScholarshipCatalogue
{
    public function all(): Collection
    {
        $base = collect($this->predefined());
        $crm = $this->crmScholarships();

        $merged = $base->keyBy(fn($x)=>Str::slug((string)($x['title']??'')));
        foreach ($crm as $row) {
            $key=Str::slug((string)($row['title']??''));
            $merged[$key]=array_merge($merged[$key]??[], $row, ['source'=>'crm']);
        }
        return $merged->values();
    }

    private function predefined(): array
    {
        return [
            ['title'=>'Chevening Scholarships','type'=>'scholarship','destinations'=>['United Kingdom'],'levels'=>['Masters'],'citizenships'=>['International'],'min_experience'=>2,'english_required'=>true,'leadership_required'=>true,'bond_required'=>true,'coverage'=>'Fully funded','official_url'=>'https://www.chevening.org/'],
            ['title'=>'Commonwealth Masters Scholarships','type'=>'scholarship','destinations'=>['United Kingdom'],'levels'=>['Masters'],'citizenships'=>['Commonwealth countries'],'english_required'=>true,'need_based'=>true,'coverage'=>'Fully funded','official_url'=>'https://cscuk.fcdo.gov.uk/'],
            ['title'=>'Fulbright Foreign Student Program','type'=>'scholarship','destinations'=>['United States'],'levels'=>['Masters','PhD'],'citizenships'=>['International'],'min_gpa'=>3.0,'english_required'=>true,'coverage'=>'Fully funded','official_url'=>'https://foreign.fulbrightonline.org/'],
            ['title'=>'Erasmus Mundus Joint Masters','type'=>'scholarship','destinations'=>['Europe'],'levels'=>['Masters'],'citizenships'=>['International'],'english_required'=>true,'coverage'=>'Fully funded','official_url'=>'https://erasmus-plus.ec.europa.eu/'],
            ['title'=>'Australia Awards Scholarships','type'=>'scholarship','destinations'=>['Australia'],'levels'=>['Undergraduate','Masters','PhD'],'citizenships'=>['Eligible partner countries'],'english_required'=>true,'bond_required'=>true,'need_based'=>true,'coverage'=>'Fully funded','official_url'=>'https://www.dfat.gov.au/people-to-people/australia-awards'],
            ['title'=>'DAAD EPOS Scholarships','type'=>'scholarship','destinations'=>['Germany'],'levels'=>['Masters','PhD'],'citizenships'=>['Developing countries'],'min_experience'=>2,'english_required'=>true,'coverage'=>'Fully funded','official_url'=>'https://www.daad.de/'],
            ['title'=>'DAAD Study Scholarships','type'=>'scholarship','destinations'=>['Germany'],'levels'=>['Masters'],'citizenships'=>['International'],'english_required'=>true,'coverage'=>'Funded','official_url'=>'https://www.daad.de/'],
            ['title'=>'MEXT Scholarship','type'=>'scholarship','destinations'=>['Japan'],'levels'=>['Undergraduate','Masters','PhD'],'citizenships'=>['International'],'coverage'=>'Fully funded','official_url'=>'https://www.mext.go.jp/'],
            ['title'=>'Stipendium Hungaricum','type'=>'scholarship','destinations'=>['Hungary'],'levels'=>['Undergraduate','Masters','PhD'],'citizenships'=>['Partner countries'],'coverage'=>'Fully funded','official_url'=>'https://stipendiumhungaricum.hu/'],
            ['title'=>'Swedish Institute Scholarships for Global Professionals','type'=>'scholarship','destinations'=>['Sweden'],'levels'=>['Masters'],'citizenships'=>['Eligible countries'],'min_experience'=>true,'leadership_required'=>true,'coverage'=>'Fully funded','official_url'=>'https://si.se/'],
            ['title'=>'Türkiye Scholarships','type'=>'scholarship','destinations'=>['Türkiye'],'levels'=>['Undergraduate','Masters','PhD'],'citizenships'=>['International'],'coverage'=>'Fully funded','official_url'=>'https://www.turkiyeburslari.gov.tr/'],
            ['title'=>'Chinese Government Scholarship','type'=>'scholarship','destinations'=>['China'],'levels'=>['Undergraduate','Masters','PhD'],'citizenships'=>['International'],'coverage'=>'Fully funded','official_url'=>'https://www.campuschina.org/'],
            ['title'=>'Swiss Government Excellence Scholarships','type'=>'scholarship','destinations'=>['Switzerland'],'levels'=>['PhD','Postdoc'],'citizenships'=>['International'],'supervisor_required'=>true,'coverage'=>'Funded','official_url'=>'https://www.sbfi.admin.ch/'],
            ['title'=>'New Zealand Manaaki Scholarships','type'=>'scholarship','destinations'=>['New Zealand'],'levels'=>['Undergraduate','Masters','PhD'],'citizenships'=>['Eligible countries'],'coverage'=>'Fully funded','official_url'=>'https://www.nzscholarships.govt.nz/'],
            ['title'=>'Gates Cambridge Scholarship','type'=>'scholarship','destinations'=>['United Kingdom'],'levels'=>['Masters','PhD'],'citizenships'=>['International'],'leadership_required'=>true,'coverage'=>'Fully funded','official_url'=>'https://www.gatescambridge.org/'],
            ['title'=>'Clarendon Scholarships','type'=>'scholarship','destinations'=>['United Kingdom'],'levels'=>['Masters','PhD'],'citizenships'=>['International'],'coverage'=>'Fully funded','official_url'=>'https://www.ox.ac.uk/clarendon'],
            ['title'=>'Rhodes Scholarship','type'=>'scholarship','destinations'=>['United Kingdom'],'levels'=>['Masters','PhD'],'citizenships'=>['Eligible constituencies'],'leadership_required'=>true,'coverage'=>'Fully funded','official_url'=>'https://www.rhodeshouse.ox.ac.uk/'],
            ['title'=>'Knight-Hennessy Scholars','type'=>'scholarship','destinations'=>['United States'],'levels'=>['Masters','PhD'],'citizenships'=>['International'],'leadership_required'=>true,'coverage'=>'Fully funded','official_url'=>'https://knight-hennessy.stanford.edu/'],
            ['title'=>'Vanier Canada Graduate Scholarships','type'=>'scholarship','destinations'=>['Canada'],'levels'=>['PhD'],'citizenships'=>['International','Canada'],'leadership_required'=>true,'coverage'=>'Funded','official_url'=>'https://vanier.gc.ca/'],
            ['title'=>'Eiffel Excellence Scholarship','type'=>'scholarship','destinations'=>['France'],'levels'=>['Masters','PhD'],'citizenships'=>['International'],'coverage'=>'Funded','official_url'=>'https://www.campusfrance.org/']
        ];
    }

    private function crmScholarships(): Collection
    {
        try {
            $exists=DB::selectOne("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='opportunities'");
            if(!(int)($exists->c??0)) return collect();
            return DB::table('opportunities')->limit(1500)->get()->map(function($r){
                $a=(array)$r; $meta=json_decode((string)($a['metadata']??''),true); if(!is_array($meta))$meta=[]; $flat=array_merge($a,$meta);
                $title=$flat['title']??$flat['name']??null; if(!$title)return null;
                $type=Str::lower((string)($flat['type']??$flat['opportunity_type']??$flat['category']??''));
                if(!Str::contains(Str::lower($title.' '.$type),'scholar')) return null;
                $status=Str::lower((string)($flat['status']??'published')); if(!in_array($status,['published','active','open','live','approved'],true))return null;
                $country=$flat['country_name']??$flat['country']??null; if(is_numeric($country))$country=null;
                return [
                    'title'=>$title,'slug'=>$flat['slug']??Str::slug($title),'type'=>'scholarship','destinations'=>$country?[$country]:[],
                    'levels'=>$this->listify($flat['levels']??$flat['study_level']??$flat['degree_level']??null),
                    'citizenships'=>$this->listify($flat['eligible_countries']??$flat['citizenship']??$flat['nationality']??null),
                    'min_gpa'=>$this->number($flat['min_gpa']??$flat['minimum_gpa']??null),
                    'min_experience'=>$this->number($flat['min_experience']??$flat['work_experience']??null),
                    'max_age'=>$this->number($flat['max_age']??$flat['age_limit']??null),
                    'english_required'=>$this->boolish($flat['english_required']??$flat['ielts_required']??null),
                    'leadership_required'=>$this->boolish($flat['leadership_required']??null),
                    'need_based'=>$this->boolish($flat['need_based']??null),'bond_required'=>$this->boolish($flat['bond_required']??null),
                    'offer_required'=>$this->boolish($flat['offer_required']??null),'supervisor_required'=>$this->boolish($flat['supervisor_required']??null),
                    'coverage'=>$flat['funding']??$flat['coverage']??$flat['award_amount']??null,'deadline'=>$flat['deadline']??$flat['application_deadline']??null,
                    'official_url'=>$flat['official_url']??$flat['application_url']??$flat['website']??null,'source'=>'crm'
                ];
            })->filter()->values();
        } catch(\Throwable $e){ report($e); return collect(); }
    }
    private function listify($v):array{if(!$v)return[];if(is_array($v))return array_values(array_filter($v));$j=json_decode((string)$v,true);if(is_array($j))return array_values(array_filter($j));return array_values(array_filter(array_map('trim',preg_split('/[,|;]+/',(string)$v)?:[])));}
    private function number($v):?float{if($v===null||$v==='')return null;if(is_numeric($v))return(float)$v;if(preg_match('/\\d+(?:\\.\\d+)?/',(string)$v,$m))return(float)$m[0];return null;}
    private function boolish($v):bool{if(is_bool($v))return$v;if(is_numeric($v))return(int)$v===1;return in_array(Str::lower(trim((string)$v)),['yes','true','1','required','on'],true);}
}
''')

w(pub/'payload/app/Services/Tools/D2dEligibilityMatcher.php', r'''<?php
namespace App\Services\Tools;

use Carbon\Carbon;
use Illuminate\Support\Str;

class D2dEligibilityMatcher
{
    public function __construct(private D2dScholarshipCatalogue $catalogue){}
    public function run(array $p): array
    {
        $p=$this->profile($p); $rows=$this->catalogue->all()->map(fn($s)=>$this->check($s,$p))->sortByDesc('score')->values();
        return ['generated_at'=>now()->toIso8601String(),'profile'=>$p,'summary'=>['total'=>$rows->count(),'eligible'=>$rows->where('status','eligible')->count(),'review'=>$rows->where('status','review')->count(),'not_eligible'=>$rows->where('status','not_eligible')->count()],'results'=>$rows->all()];
    }
    private function profile(array $p):array{ $age=null;if(!empty($p['dob']))try{$age=Carbon::parse($p['dob'])->age;}catch(\Throwable $e){} $gpa=($p['gpa']??'')!==''?(float)$p['gpa']:null;$scale=(string)($p['gpa_scale']??'4.0');$g4=$gpa===null?null:($scale==='10.0'?$gpa/10*4:($scale==='Percentage'?$gpa/100*4:$gpa));return ['citizenship'=>trim((string)($p['citizenship']??'')),'dob'=>$p['dob']??null,'age'=>$age,'experience'=>(float)($p['experience']??0),'current_level'=>$p['current_level']??'','target_level'=>$p['target_level']??'','field'=>$p['field']??'','gpa'=>$gpa,'gpa_scale'=>$scale,'gpa_4'=>$g4,'tests'=>(array)($p['tests']??[]),'flags'=>(array)($p['flags']??[]),'constraints'=>(array)($p['constraints']??[])];}
    private function check(array $s,array $p):array{
        $hard=[];$review=[];$good=[];$checked=0;
        if(!empty($s['levels'])){$checked++;$target=Str::lower($p['target_level']);$ok=collect($s['levels'])->contains(fn($x)=>Str::contains(Str::lower((string)$x),$target)||Str::contains($target,Str::lower((string)$x)));$ok?$good[]='Degree level matches.':$hard[]='Target degree level does not match.';}
        if(!empty($s['citizenships'])){$checked++;$list=Str::lower(implode(' ',(array)$s['citizenships']));$cit=Str::lower($p['citizenship']);$broad=Str::contains($list,['international','eligible','commonwealth','partner','developing']);$ok=$broad||($cit&&Str::contains($list,$cit));$ok?$good[]='Citizenship appears eligible.':$review[]='Citizenship eligibility needs manual confirmation.';}
        if(isset($s['min_gpa'])&&$s['min_gpa']!==null){$checked++;if($p['gpa_4']===null)$review[]='GPA requirement exists but GPA was not provided.';elseif($p['gpa_4']<(float)$s['min_gpa'])$hard[]='GPA is below the stored minimum.';else$good[]='GPA meets the stored minimum.';}
        if(!empty($s['min_experience'])){$checked++;((float)$p['experience']<(float)$s['min_experience'])?$hard[]='Work experience is below the stored minimum.':$good[]='Work experience meets the stored minimum.';}
        if(!empty($s['max_age'])&&$p['age']!==null){$checked++;$p['age']>(int)$s['max_age']?$hard[]='Age is above the stored limit.':$good[]='Age requirement is met.';}
        if(!empty($s['english_required'])){$checked++;(in_array('IELTS',$p['tests'],true)||in_array('TOEFL',$p['tests'],true))?$good[]='English-test readiness indicated.':$review[]='English-language evidence appears required.';}
        if(!empty($s['leadership_required'])){$checked++;in_array('Leadership',$p['flags'],true)?$good[]='Leadership indicated.':$review[]='Leadership evidence may be required.';}
        if(!empty($s['need_based'])){$checked++;in_array('Low Income',$p['flags'],true)?$good[]='Financial-need profile indicated.':$review[]='Financial-need evidence may be relevant.';}
        if(!empty($s['bond_required'])){$checked++;in_array('No Bond',$p['constraints'],true)?$hard[]='Return/service bond conflicts with your preference.':$review[]='Scholarship may include a return/service bond.';}
        if(!empty($s['offer_required'])){$checked++;in_array('Offer Ready',$p['constraints'],true)?$good[]='Admission offer indicated.':$review[]='Admission offer may be required.';}
        if(!empty($s['supervisor_required'])){$checked++;in_array('Supervisor Ready',$p['constraints'],true)?$good[]='Supervisor readiness indicated.':$review[]='Supervisor may be required.';}
        if($checked===0)$review[]='Not enough structured eligibility fields are available for an automatic decision.';
        $status=$hard?'not_eligible':($review?'review':'eligible');$score=max(0,min(100,100-count($hard)*30-count($review)*8));
        return array_merge($s,['status'=>$status,'score'=>$score,'criteria_checked'=>$checked,'positive_reasons'=>$good,'review_reasons'=>$review,'hard_reasons'=>$hard]);
    }
}
''')

w(pub/'payload/app/Http/Controllers/Tools/D2dEligibilityController.php', r'''<?php
namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\Tools\D2dEligibilityMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class D2dEligibilityController extends Controller
{
    public function index(){return view('tools.d2d-eligibility-v3');}
    public function check(Request $r,D2dEligibilityMatcher $m){$p=$r->validate(['citizenship'=>'required|string|max:120','dob'=>'nullable|date','experience'=>'nullable|numeric|min:0|max:80','current_level'=>'nullable|string|max:80','target_level'=>'required|string|max:80','field'=>'nullable|string|max:160','gpa'=>'nullable|numeric|min:0|max:100','gpa_scale'=>'nullable|string|max:20','tests'=>'nullable|array','flags'=>'nullable|array','constraints'=>'nullable|array']);$result=$m->run($p);$token=Str::random(48);session()->put('d2d_elig_v3_'.$token,['profile'=>$p,'result'=>$result,'at'=>time()]);return response()->json(['ok'=>true,'run_token'=>$token,'data'=>$result]);}
    public function save(Request $r){$r->validate(['run_token'=>'required|string|size:48']);$run=session()->get('d2d_elig_v3_'.$r->run_token);abort_unless(is_array($run),422,'Result expired. Run the checker again.');$claim=Str::random(56);$public=(string)Str::uuid();DB::table('d2d_student_tool_results')->insert(['public_id'=>$public,'user_id'=>null,'tool_key'=>'eligibility-checker','title'=>'Scholarship Eligibility Check','input_json'=>json_encode($run['profile']),'result_json'=>json_encode($run['result']),'summary_json'=>json_encode($run['result']['summary']??[]),'claim_token'=>$claim,'share_token'=>null,'is_shared'=>0,'created_at'=>now(),'updated_at'=>now()]);session()->forget('d2d_elig_v3_'.$r->run_token);return response()->json(['ok'=>true,'redirect'=>'https://portal.dares2dream.com/tool-results/claim/'.$claim]);}
}
''')

w(pub/'payload/app/Providers/D2dEligibilityV3ServiceProvider.php', r'''<?php
namespace App\Providers;
use App\Http\Controllers\Tools\D2dEligibilityController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
class D2dEligibilityV3ServiceProvider extends ServiceProvider{public function register():void{} public function boot():void{Route::middleware('web')->group(function(){Route::get('/tools/eligibility-checker',[D2dEligibilityController::class,'index']);Route::post('/tools/eligibility-checker/check',[D2dEligibilityController::class,'check']);Route::post('/tools/eligibility-checker/save',[D2dEligibilityController::class,'save']);});if($this->app->runningInConsole()&&is_file(base_path('routes/d2d-eligibility-v3-console.php')))require base_path('routes/d2d-eligibility-v3-console.php');}}
''')

w(pub/'payload/resources/views/tools/d2d-eligibility-v3.blade.php', r'''<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Scholarship Eligibility Checker — D2D</title><link rel="stylesheet" href="/v11/styles.css"><link rel="stylesheet" href="/d2d-tools/eligibility-v3.css?v=3"></head><body class="ev3"><header><a href="/" class="brand">DARE TO DREAM</a><nav><a href="/scholarships">Scholarships</a><a href="/universities">Universities</a><a href="/opportunities">Opportunities</a><a href="/jobs">Jobs Abroad</a><a href="/internships">Internships</a><a class="active" href="/tools">Tools</a></nav><a href="https://portal.dares2dream.com/">Log in</a></header><section class="hero"><span>FREE D2D STUDENT TOOL</span><h1>SCHOLARSHIP<br><em>ELIGIBILITY</em> CHECKER</h1><p>Check one profile against the full D2D scholarship catalogue plus current scholarship records published from CRM.</p></section><main><aside><b>YOUR CHECK</b><button class="step on" data-go="1">01 Profile</button><button class="step" data-go="2">02 Academics</button><button class="step" data-go="3">03 Readiness</button><button class="step" data-go="4">04 Results</button><a href="https://portal.dares2dream.com/saved-results">Saved Results →</a></aside><section class="stage"><div class="bar"><i></i></div><form id="f"><section class="panel on" data-p="1"><span>STEP 01</span><h2>Build your profile.</h2><div class="grid"><label>Citizenship<input name="citizenship" required placeholder="Pakistan"></label><label>Date of birth<input name="dob" type="date"></label><label>Work experience<input name="experience" type="number" step="0.1" min="0" placeholder="2"></label><label>Highest degree<select name="current_level"><option>High School</option><option>Undergraduate</option><option>Masters</option><option>PhD</option></select></label></div><footer><i></i><button type="button" data-next="2">Academic details →</button></footer></section><section class="panel" data-p="2"><span>STEP 02</span><h2>What are you aiming for?</h2><div class="grid"><label>Target degree<select name="target_level"><option>Undergraduate</option><option selected>Masters</option><option>PhD</option><option>Postdoc</option></select></label><label>Field of study<input name="field" placeholder="Computer Science"></label><label>GPA / marks<input name="gpa" type="number" step="0.01"></label><label>Scale<select name="gpa_scale"><option>4.0</option><option>10.0</option><option>Percentage</option></select></label></div><footer><button type="button" data-back="1">← Back</button><button type="button" data-next="3">Readiness →</button></footer></section><section class="panel" data-p="3"><span>STEP 03</span><h2>Add readiness signals.</h2><h3>Tests</h3><div class="choices" data-g="tests"><button type="button" data-v="IELTS">IELTS</button><button type="button" data-v="TOEFL">TOEFL</button><button type="button" data-v="GRE">GRE</button><button type="button" data-v="GMAT">GMAT</button></div><h3>Profile strengths</h3><div class="choices" data-g="flags"><button type="button" data-v="Leadership">Leadership</button><button type="button" data-v="Low Income">Financial Need</button><button type="button" data-v="Research">Research</button></div><h3>Constraints / readiness</h3><div class="choices" data-g="constraints"><button type="button" data-v="No Bond">No return bond</button><button type="button" data-v="Offer Ready">Have offer</button><button type="button" data-v="Supervisor Ready">Have supervisor</button></div><footer><button type="button" data-back="2">← Back</button><button class="gold" type="submit">Run my D2D check →</button></footer></section><section class="panel" data-p="4"><span>STEP 04</span><h2>Your scholarship snapshot.</h2><div id="summary" class="loading">Run the checker to see your results.</div><div id="results"></div><div id="save" class="save" hidden><div><b>SAVE THIS RESULT</b><strong>Keep it in your existing D2D Portal account.</strong><small>Share it later or download a branded PDF.</small></div><button type="button" id="saveBtn">Save to my D2D account →</button></div><footer><button type="button" data-back="3">← Edit profile</button><button type="button" data-go="1">Run again</button></footer></section></form></section></main><script src="/d2d-tools/eligibility-v3.js?v=3" defer></script></body></html>''')

w(pub/'payload/public/d2d-tools/eligibility-v3.css', r''':root{--g:#f4af00;--b:#0c0d0f;--m:#75777e;--l:#d9dade}*{box-sizing:border-box}body.ev3{margin:0;background:#f4f4f1;color:#15161a;font-family:Manrope,Arial,sans-serif}header{height:88px;background:#fff;display:flex;align-items:center;gap:28px;padding:0 max(24px,calc((100vw - 1400px)/2));border-bottom:1px solid #eee}header .brand{font:700 27px Oswald,Arial;color:#111;text-decoration:none}header nav{display:flex;gap:25px;margin:auto}header a{color:#111;text-decoration:none;font-size:11px;font-weight:650}header nav .active{border-bottom:2px solid var(--g);padding-bottom:5px}.hero{padding:70px max(28px,calc((100vw - 1320px)/2));background:#0d0e10;color:#fff}.hero>span,.panel>span{color:var(--g);font-size:9px;font-weight:800;letter-spacing:.15em}.hero h1{font:700 clamp(58px,7vw,96px)/.9 Oswald,Arial;margin:12px 0}.hero h1 em{color:var(--g);font-style:normal}.hero p{max-width:820px;color:#c5c7cc;line-height:1.65}main{width:min(1320px,calc(100% - 36px));margin:42px auto 80px;display:grid;grid-template-columns:260px 1fr;gap:20px}aside{position:sticky;top:18px;background:#101114;color:#fff;border-radius:16px;padding:18px;align-self:start}aside>b{display:block;color:#868990;font-size:8px;letter-spacing:.14em;margin-bottom:10px}.step{width:100%;border:0;border-top:1px solid #28292d;background:transparent;color:#aaa;text-align:left;padding:15px 8px;font-weight:700}.step.on{color:var(--g)}aside>a{display:block;margin-top:20px;color:var(--g);font-size:10px}.stage{background:#fff;border:1px solid var(--l);border-radius:16px;overflow:hidden}.bar{height:4px;background:#eee}.bar i{display:block;height:4px;width:25%;background:var(--g)}.panel{display:none;padding:32px}.panel.on{display:block}.panel h2{font-size:31px;margin:8px 0 28px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.grid label{font-size:10px;font-weight:750}.grid input,.grid select{display:block;width:100%;height:50px;margin-top:8px;border:1px solid #cfd0d4;border-radius:9px;padding:0 13px;background:#fff}.panel h3{margin:24px 0 10px;font-size:10px;color:#777;letter-spacing:.12em}.choices{display:grid;grid-template-columns:repeat(3,1fr);gap:9px}.choices button{min-height:54px;border:1px solid #d2d3d7;border-radius:10px;background:#fff;font-weight:700}.choices button.on{background:#111;color:var(--g);border-color:#111}.panel footer{display:flex;justify-content:space-between;margin-top:30px;padding-top:22px;border-top:1px solid #eee}.panel footer button,.save button{min-height:44px;border:1px solid #ccd0d4;border-radius:8px;background:#fff;color:#111;padding:0 16px;font-weight:800}.panel footer button:last-child,.panel footer .gold,.save button{background:var(--g);border-color:var(--g)}.loading{padding:30px;border:1px dashed #ccc;border-radius:10px;text-align:center;color:#777}.dash{display:grid;grid-template-columns:1.2fr repeat(3,.7fr);gap:8px;margin-bottom:18px}.dash>div{border:1px solid #ddd;border-radius:11px;padding:16px}.dash .score{background:#111;color:#fff}.dash span{display:block;color:#8b8e94;font-size:8px}.dash strong{display:block;font-size:24px;margin-top:6px}.dash .score strong{color:var(--g);font-size:30px}.result{padding:16px 0;border-bottom:1px solid #eee}.rh{display:flex;justify-content:space-between;gap:15px}.rh h3{margin:0;color:#111;font-size:14px;letter-spacing:0}.pct{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#f1f1ef;font-size:10px;font-weight:800}.meta{color:#878990;font-size:9px;margin-top:5px}.badge{display:inline-block;border-radius:999px;padding:5px 8px;font-size:8px;font-weight:800;margin-top:9px}.badge.eligible{background:#111;color:#fff}.badge.review{background:#fff0bd;color:#755300}.badge.not_eligible{background:#eee;color:#555}.why{font-size:9px;color:#606269;margin-top:7px}.result a{font-size:9px;color:#111;font-weight:800}.save{display:flex;justify-content:space-between;align-items:center;gap:20px;background:#fff8dd;border:1px solid #edcf68;border-radius:12px;padding:18px;margin-top:22px}.save b{display:block;color:#9a6800;font-size:8px}.save strong{display:block;margin:5px 0}.save small{color:#756c55}@media(max-width:950px){header nav{display:none}main{grid-template-columns:1fr}aside{position:static}.grid,.choices,.dash{grid-template-columns:1fr}}''')

w(pub/'payload/public/d2d-tools/eligibility-v3.js', r'''(()=>{"use strict";const f=document.getElementById('f');if(!f)return;const csrf=document.querySelector('meta[name=csrf-token]').content;const panels=[...document.querySelectorAll('[data-p]')],steps=[...document.querySelectorAll('.step')],bar=document.querySelector('.bar i'),summary=document.getElementById('summary'),results=document.getElementById('results'),save=document.getElementById('save');let run=null,current=1;const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));function go(n){current=+n;panels.forEach(x=>x.classList.toggle('on',+x.dataset.p===current));steps.forEach(x=>x.classList.toggle('on',+x.dataset.go===current));bar.style.width=(current*25)+'%'}document.querySelectorAll('[data-next]').forEach(b=>b.onclick=()=>go(b.dataset.next));document.querySelectorAll('[data-back]').forEach(b=>b.onclick=()=>go(b.dataset.back));document.querySelectorAll('[data-go]').forEach(b=>b.onclick=()=>go(b.dataset.go));document.querySelectorAll('.choices button').forEach(b=>b.onclick=()=>b.classList.toggle('on'));const vals=g=>[...document.querySelectorAll('[data-g="'+g+'"] button.on')].map(b=>b.dataset.v);function payload(){let d=new FormData(f);return{citizenship:d.get('citizenship'),dob:d.get('dob')||null,experience:d.get('experience')||0,current_level:d.get('current_level'),target_level:d.get('target_level'),field:d.get('field')||'',gpa:d.get('gpa')||null,gpa_scale:d.get('gpa_scale'),tests:vals('tests'),flags:vals('flags'),constraints:vals('constraints')}}function render(d){let s=d.summary||{},total=Math.max(1,s.total||0),read=Math.round(((s.eligible||0)+(s.review||0)*.45)/total*100);summary.className='';summary.innerHTML='<div class="dash"><div class="score"><span>D2D READINESS</span><strong>'+read+'%</strong></div><div><span>ELIGIBLE</span><strong>'+(s.eligible||0)+'</strong></div><div><span>REVIEW</span><strong>'+(s.review||0)+'</strong></div><div><span>NOT ELIGIBLE</span><strong>'+(s.not_eligible||0)+'</strong></div></div>';results.innerHTML=(d.results||[]).map(x=>{let note=(x.hard_reasons||[])[0]||(x.review_reasons||[])[0]||(x.positive_reasons||[])[0]||'Review official criteria.';let url=x.official_url||('/scholarships/'+encodeURIComponent(x.slug||''));return '<article class="result"><div class="rh"><div><h3>'+esc(x.title)+'</h3><div class="meta">'+esc((x.destinations||[]).join(', ')||'Global')+(x.coverage?' · '+esc(x.coverage):'')+'</div></div><div class="pct">'+esc(x.score)+'</div></div><span class="badge '+esc(x.status)+'">'+esc(x.status.replace('_',' '))+'</span><div class="why">'+esc(note)+'</div><a href="'+esc(url)+'" target="_blank">Review scholarship →</a></article>'}).join('');save.hidden=!(d.results||[]).length}f.onsubmit=async e=>{e.preventDefault();go(4);summary.className='loading';summary.textContent='Checking the full D2D scholarship catalogue…';results.innerHTML='';save.hidden=true;let r=await fetch('/tools/eligibility-checker/check',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload())});let j=await r.json();if(!r.ok){summary.textContent=j.message||'Could not run check.';return}run=j.run_token;render(j.data)};document.getElementById('saveBtn').onclick=async()=>{if(!run)return;let r=await fetch('/tools/eligibility-checker/save',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({run_token:run})});let j=await r.json();if(!r.ok)return alert(j.message||'Could not save.');location.href=j.redirect};go(1)})();''')

# ---------- portal ----------
w(portal/'payload/app/Providers/D2dSavedResultsV3ServiceProvider.php', r'''<?php
namespace App\Providers;
use App\Http\Controllers\D2dSavedResultsV3Controller;use Illuminate\Support\Facades\Route;use Illuminate\Support\ServiceProvider;
class D2dSavedResultsV3ServiceProvider extends ServiceProvider{public function register():void{}public function boot():void{Route::middleware(['web','auth'])->group(function(){Route::get('/tool-results/claim/{token}',[D2dSavedResultsV3Controller::class,'claim']);Route::get('/saved-results',[D2dSavedResultsV3Controller::class,'index']);Route::get('/saved-results/{id}',[D2dSavedResultsV3Controller::class,'show']);Route::post('/saved-results/{id}/share',[D2dSavedResultsV3Controller::class,'share']);Route::post('/saved-results/{id}/share/revoke',[D2dSavedResultsV3Controller::class,'revoke']);Route::delete('/saved-results/{id}',[D2dSavedResultsV3Controller::class,'destroy']);});if($this->app->runningInConsole()&&is_file(base_path('routes/d2d-saved-v3-console.php')))require base_path('routes/d2d-saved-v3-console.php');}}
''')

w(portal/'payload/app/Http/Controllers/D2dSavedResultsV3Controller.php', r'''<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;use Illuminate\Support\Str;
class D2dSavedResultsV3Controller extends Controller{public function claim($token){$row=DB::table('d2d_student_tool_results')->where('claim_token',$token)->first();abort_unless($row,404);$uid=auth()->id();if($row->user_id&&((string)$row->user_id!==(string)$uid))abort(403);DB::table('d2d_student_tool_results')->where('id',$row->id)->update(['user_id'=>$uid,'claim_token'=>null,'updated_at'=>now()]);return redirect('/saved-results/'.$row->public_id)->with('status','Result saved to your D2D account.');}public function index(){return view('d2d-saved-results-v3.index',['rows'=>DB::table('d2d_student_tool_results')->where('user_id',auth()->id())->orderByDesc('created_at')->get()]);}public function show($id){$row=$this->owned($id);return view('d2d-saved-results-v3.show',['row'=>$row,'result'=>json_decode($row->result_json,true)?:[],'profile'=>json_decode($row->input_json,true)?:[]]);}public function share($id){$row=$this->owned($id);$t=$row->share_token?:Str::random(48);DB::table('d2d_student_tool_results')->where('id',$row->id)->update(['share_token'=>$t,'is_shared'=>1,'updated_at'=>now()]);return back()->with('share_url','https://dares2dream.com/results/share/'.$t);}public function revoke($id){$row=$this->owned($id);DB::table('d2d_student_tool_results')->where('id',$row->id)->update(['share_token'=>null,'is_shared'=>0,'updated_at'=>now()]);return back()->with('status','Sharing disabled.');}public function destroy($id){$row=$this->owned($id);DB::table('d2d_student_tool_results')->where('id',$row->id)->delete();return redirect('/saved-results');}private function owned($id){$r=DB::table('d2d_student_tool_results')->where('public_id',$id)->where('user_id',auth()->id())->first();abort_unless($r,404);return$r;}}
''')

w(portal/'payload/resources/views/d2d-saved-results-v3/index.blade.php', r'''<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Saved Results — D2D Portal</title><link rel="stylesheet" href="/d2d-saved-v3.css?v=3"></head><body><main><a href="/student" class="back">← Student Portal</a><header><div><span>SAVED RESULTS</span><h1>Your D2D tool history</h1><p>Tool results saved under this existing student account.</p></div><a href="https://dares2dream.com/tools/eligibility-checker">Run Eligibility Checker →</a></header><section class="grid">@forelse($rows as $row) @php($s=json_decode($row->summary_json,true)?:[])<article><span>{{ strtoupper(str_replace('-',' ',$row->tool_key)) }}</span><h2>{{ $row->title }}</h2><small>{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('M j, Y · g:i A') }}</small><div class="stats"><b>{{ $s['eligible']??0 }}<i>Eligible</i></b><b>{{ $s['review']??0 }}<i>Review</i></b><b>{{ $s['not_eligible']??0 }}<i>Not eligible</i></b></div><a href="/saved-results/{{ $row->public_id }}">View result →</a></article>@empty <div class="empty">No saved results yet.</div>@endforelse</section></main></body></html>''')

w(portal/'payload/resources/views/d2d-saved-results-v3/show.blade.php', r'''<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Saved Eligibility Result — D2D Portal</title><link rel="stylesheet" href="/d2d-saved-v3.css?v=3"></head><body><main><a href="/saved-results" class="back">← Saved Results</a>@if(session('status'))<div class="notice">{{ session('status') }}</div>@endif @if(session('share_url'))<div class="notice">Share URL: <input value="{{ session('share_url') }}" readonly></div>@endif @php($s=$result['summary']??[])<header><div><span>ELIGIBILITY CHECKER</span><h1>{{ $row->title }}</h1><p>Saved {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('M j, Y · g:i A') }}</p></div><div class="actions">@if(!$row->is_shared)<form method="post" action="/saved-results/{{ $row->public_id }}/share">@csrf<button>Share result</button></form>@else<form method="post" action="/saved-results/{{ $row->public_id }}/share/revoke">@csrf<button>Disable sharing</button></form>@endif</div></header><div class="stats big"><b>{{ $s['eligible']??0 }}<i>Eligible</i></b><b>{{ $s['review']??0 }}<i>Needs review</i></b><b>{{ $s['not_eligible']??0 }}<i>Not eligible</i></b></div><h2>Scholarship matches</h2>@foreach($result['results']??[] as $x)<article class="row"><div><strong>{{ $x['title']??'Scholarship' }}</strong><small>{{ implode(', ',$x['destinations']??[]) }}</small><p>{{ ($x['hard_reasons'][0]??$x['review_reasons'][0]??$x['positive_reasons'][0]??'Review official criteria.') }}</p></div><span class="badge {{ $x['status']??'review' }}">{{ str_replace('_',' ',ucfirst($x['status']??'review')) }}</span></article>@endforeach<form method="post" action="/saved-results/{{ $row->public_id }}" onsubmit="return confirm('Delete this saved result?')">@csrf @method('DELETE')<button class="delete">Delete saved result</button></form></main></body></html>''')

w(portal/'payload/public/d2d-saved-v3.css', r'''*{box-sizing:border-box}body{margin:0;background:#f4f4f1;color:#15161a;font-family:Manrope,Arial,sans-serif}main{width:min(1180px,calc(100% - 36px));margin:38px auto 70px}.back{color:#111;font-size:10px}header{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;margin:22px 0}header span{color:#9a6900;font-size:8px;font-weight:800;letter-spacing:.14em}h1{margin:6px 0;font-size:34px}header p{margin:0;color:#777;font-size:11px}header>a,article>a,button{display:inline-flex;min-height:42px;align-items:center;padding:0 14px;border-radius:8px;border:1px solid #f4af00;background:#f4af00;color:#111;text-decoration:none;font-size:10px;font-weight:800}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.grid article{background:#fff;border:1px solid #dadbde;border-radius:16px;padding:22px}.grid article>span{font-size:8px;color:#9a6900}.grid h2{font-size:17px}.grid small,.row small{display:block;color:#888;font-size:9px}.stats{display:grid;grid-template-columns:repeat(3,1fr);border:1px solid #e0e1e4;border-radius:9px;overflow:hidden;margin:16px 0}.stats b{text-align:center;padding:12px;font-size:17px}.stats b+b{border-left:1px solid #e0e1e4}.stats i{display:block;margin-top:4px;font-style:normal;font-size:8px;color:#999}.stats.big{max-width:600px}.row{display:flex;justify-content:space-between;gap:20px;padding:16px 0;border-bottom:1px solid #eee}.row p{font-size:9px;color:#666}.badge{height:max-content;border-radius:999px;padding:6px 9px;font-size:8px;font-weight:800}.badge.eligible{background:#111;color:#fff}.badge.review{background:#fff0bd;color:#755300}.badge.not_eligible{background:#eee;color:#555}.notice{padding:12px;background:#fff8da;border:1px solid #eed06d;border-radius:9px}.notice input{width:70%}.delete{margin-top:28px;background:#fff;border-color:#bbb;color:#922}.empty{padding:40px;background:#fff;border:1px dashed #ccc}@media(max-width:850px){.grid{grid-template-columns:1fr}header{align-items:flex-start;flex-direction:column}}''')

# ---------- installers ----------
TABLE="""CREATE TABLE IF NOT EXISTS d2d_student_tool_results (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id CHAR(36) NOT NULL, user_id BIGINT UNSIGNED NULL, tool_key VARCHAR(80) NOT NULL, title VARCHAR(255) NOT NULL, input_json LONGTEXT NOT NULL, result_json LONGTEXT NOT NULL, summary_json LONGTEXT NULL, claim_token VARCHAR(80) NULL, share_token VARCHAR(80) NULL, is_shared TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, PRIMARY KEY(id), UNIQUE KEY uq_public(public_id), UNIQUE KEY uq_claim(claim_token), UNIQUE KEY uq_share(share_token), KEY ix_user(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"""

def installer(kind,provider,targets,name):
    forbid="portal.dares2dream.com" if kind=='public' else "d2d-laravel"
    return f'''<?php\ndeclare(strict_types=1);$base=__DIR__;$payload=$base.'/payload';echo "=== {name} ===\\n";if(!is_file($base.'/artisan')){{fwrite(STDERR,"artisan not found\\n");exit(1);}}if(str_contains($base,'{forbid}')||str_contains($base,'crm.dares2dream.com')){{fwrite(STDERR,"Wrong application\\n");exit(1);}}$backup=$base.'/storage/app/d2d-tool-backups/{kind}-'.date('Ymd-His');@mkdir($backup,0775,true);$targets={repr(targets)};foreach($targets as $rel){{$src=$payload.'/'.$rel;$dst=$base.'/'.$rel;if(!is_file($src)){{fwrite(STDERR,"Missing $rel\\n");exit(1);}}if(is_file($dst)){{@mkdir(dirname($backup.'/'.$rel),0775,true);copy($dst,$backup.'/'.$rel);}}@mkdir(dirname($dst),0775,true);copy($src,$dst);echo "Installed: $rel\\n";}}$pfile=$base.'/bootstrap/providers.php';copy($pfile,$backup.'/providers.php');$c=file_get_contents($pfile);$provider='{provider}';if(!str_contains($c,$provider)){{$pos=strrpos($c,'];');$before=rtrim(substr($c,0,$pos));$comma=str_ends_with($before,',')?'':',';$c=$before.$comma."\\n    ".$provider.",\\n".substr($c,$pos);file_put_contents($pfile,$c);}}require $base.'/vendor/autoload.php';$app=require $base.'/bootstrap/app.php';$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();\\Illuminate\\Support\\Facades\\DB::statement(<<<'SQL'\n{TABLE}\nSQL);echo "Shared saved-results table ready.\\nNo existing business tables were altered.\\nRun php artisan optimize:clear\\n";'''

pub_targets=['app/Providers/D2dEligibilityV3ServiceProvider.php','app/Http/Controllers/Tools/D2dEligibilityController.php','app/Services/Tools/D2dScholarshipCatalogue.php','app/Services/Tools/D2dEligibilityMatcher.php','resources/views/tools/d2d-eligibility-v3.blade.php','public/d2d-tools/eligibility-v3.css','public/d2d-tools/eligibility-v3.js']
portal_targets=['app/Providers/D2dSavedResultsV3ServiceProvider.php','app/Http/Controllers/D2dSavedResultsV3Controller.php','resources/views/d2d-saved-results-v3/index.blade.php','resources/views/d2d-saved-results-v3/show.blade.php','public/d2d-saved-v3.css']
w(pub/'install-d2d-eligibility-v3.php',installer('public','App\\Providers\\D2dEligibilityV3ServiceProvider::class',pub_targets,'D2D ELIGIBILITY V3'))
w(portal/'install-d2d-portal-results-v3.php',installer('portal','App\\Providers\\D2dSavedResultsV3ServiceProvider::class',portal_targets,'D2D PORTAL RESULTS V3'))

w(OUT/'README.md', '''# D2D Tool #1 — Eligibility Checker V3 + Portal Saved Results

Install Portal first:

```bash
cd ~/portal.dares2dream.com
php install-d2d-portal-results-v3.php
php artisan optimize:clear
```

Then install public tool:

```bash
cd ~/d2d-laravel
php install-d2d-eligibility-v3.php
php artisan optimize:clear
```

Public tool: `https://dares2dream.com/tools/eligibility-checker`
Portal saved results: `https://portal.dares2dream.com/saved-results`

Safety: one additive table only (`d2d_student_tool_results`). No reset, refresh, rollback, or destructive migration. No Portal navigation injection.
''')

if ZIP.exists(): ZIP.unlink()
with zipfile.ZipFile(ZIP,'w',zipfile.ZIP_DEFLATED) as z:
    for p in OUT.rglob('*'):
        if p.is_file(): z.write(p,p.relative_to(OUT))
print(ZIP)
