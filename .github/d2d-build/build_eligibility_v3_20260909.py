from pathlib import Path
import shutil, subprocess, zipfile, textwrap, os

ROOT=Path('build/d2d-eligibility-v3')
OUT=Path('build/d2d-tool1-eligibility-portal-v3.zip')
if ROOT.exists(): shutil.rmtree(ROOT)
if OUT.exists(): OUT.unlink()
PUB=ROOT/'public-app'
PORTAL=ROOT/'portal-app'

def w(base, rel, content):
    p=base/'payload'/rel
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(textwrap.dedent(content).lstrip(), encoding='utf-8')

# ---------------- PUBLIC APP ----------------
w(PUB,'app/Providers/D2dEligibilityV3ServiceProvider.php',r'''
<?php
namespace App\Providers;

use App\Http\Controllers\Tools\D2dEligibilityV3Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class D2dEligibilityV3ServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Route::middleware('web')->group(function () {
            Route::get('/tools/eligibility-checker', [D2dEligibilityV3Controller::class,'index'])->name('d2d.eligibility.v3');
            Route::get('/eligibility-checker', [D2dEligibilityV3Controller::class,'index']);
            Route::post('/tools/eligibility-checker/check', [D2dEligibilityV3Controller::class,'check'])->name('d2d.eligibility.v3.check');
            Route::post('/tools/eligibility-checker/save', [D2dEligibilityV3Controller::class,'save'])->name('d2d.eligibility.v3.save');
            Route::get('/results/share/{token}', [D2dEligibilityV3Controller::class,'share'])->where('token','[A-Za-z0-9]{40,80}');
        });
        if ($this->app->runningInConsole()) require base_path('routes/d2d-eligibility-v3-console.php');
    }
}
''')

w(PUB,'app/Services/Tools/D2dScholarshipCatalog.php',r'''
<?php
namespace App\Services\Tools;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class D2dScholarshipCatalog
{
    public function all(): Collection
    {
        $base=collect($this->predefined())->keyBy(fn($x)=>$x['id']);
        foreach($this->crm() as $item){
            $id=$this->canonicalId($item);
            if($base->has($id)) $base[$id]=$this->merge($base[$id],$item);
            else $base[$id]=$item;
        }
        return $base->values();
    }

    public function predefined(): array
    {
        $s=[];
        $add=function(string $id,string $title,array $a=[]) use (&$s){
            $defaults=[
                'id'=>$id,'title'=>$title,'sponsor'=>null,'destination_countries'=>[],'eligible_citizenships'=>['Any'],
                'citizen_rule'=>'any','degree_levels'=>[],'fields_of_study'=>['Any'],'min_gpa'=>null,'gpa_scale'=>4.0,
                'test_requirements'=>[],'min_ielts'=>null,'min_toefl'=>null,'experience_required'=>false,'min_work_years'=>0,
                'age_max'=>null,'leadership_required'=>false,'need_based'=>false,'bond_required'=>false,'bond_years'=>0,
                'offer_required'=>false,'supervisor_required'=>false,'research_required'=>false,'math_focus'=>false,
                'public_policy'=>false,'dev_focus'=>false,'women_only'=>false,'african_only'=>false,'coverage'=>[],
                'deadline'=>null,'official_url'=>null,'docs_required'=>[],'selection_criteria'=>[],'value_description'=>null,
                'source'=>'predefined','crm_slug'=>null
            ];
            $s[]=array_replace_recursive($defaults,$a);
        };

        $add('chevening','Chevening Scholarships',[
            'sponsor'=>'UK Government','destination_countries'=>['UK'],'eligible_citizenships'=>['Pakistan','India','Bangladesh','Nepal','Sri Lanka','South Africa','Nigeria','Ghana','Kenya','Any'],
            'citizen_rule'=>'list','degree_levels'=>['Masters'],'min_gpa'=>3.0,'test_requirements'=>['IELTS','TOEFL'],
            'experience_required'=>true,'min_work_years'=>2,'leadership_required'=>true,'bond_required'=>true,'bond_years'=>2,
            'coverage'=>['tuition'=>true,'stipend'=>true,'airfare'=>true],'deadline'=>'2025-11-05','official_url'=>'https://www.chevening.org',
            'docs_required'=>['lors'=>2,'sop'=>true,'passport'=>true],'selection_criteria'=>['merit','leadership','networking','development impact'],
            'value_description'=>'Full tuition + living allowance + airfare'
        ]);
        $add('commonwealth_masters','Commonwealth Master’s Scholarships',[
            'sponsor'=>'Commonwealth Scholarship Commission','destination_countries'=>['UK'],'eligible_citizenships'=>['Pakistan','India','Bangladesh','Sri Lanka','South Africa','Ghana','Nigeria','Kenya'],
            'citizen_rule'=>'list','degree_levels'=>['Masters'],'test_requirements'=>['IELTS','TOEFL'],'need_based'=>true,
            'coverage'=>['tuition'=>true,'stipend'=>true,'airfare'=>true],'deadline'=>'2025-12-15','official_url'=>'https://cscuk.fcdo.gov.uk'
        ]);
        $add('commonwealth_phd','Commonwealth PhD Scholarships',[
            'sponsor'=>'Commonwealth Scholarship Commission','destination_countries'=>['UK'],'eligible_citizenships'=>['Pakistan','India','Bangladesh','Sri Lanka','South Africa','Ghana','Nigeria','Kenya'],
            'citizen_rule'=>'list','degree_levels'=>['PhD'],'test_requirements'=>['IELTS','TOEFL'],'coverage'=>['tuition'=>true,'stipend'=>true,'airfare'=>true],
            'deadline'=>'2025-12-15','official_url'=>'https://cscuk.fcdo.gov.uk'
        ]);
        $add('clarendon','Clarendon Scholarships (Oxford)',['destination_countries'=>['UK'],'degree_levels'=>['Masters','PhD'],'min_gpa'=>3.7,'deadline'=>'2025-12-01','official_url'=>'https://www.ox.ac.uk/clarendon']);
        $add('gates_cambridge','Gates Cambridge Scholarships',[
            'sponsor'=>'Gates Cambridge','destination_countries'=>['UK'],'degree_levels'=>['Masters','PhD'],'min_gpa'=>3.8,'test_requirements'=>['IELTS','TOEFL'],'min_ielts'=>7.5,
            'leadership_required'=>true,'coverage'=>['tuition'=>true,'stipend'=>true,'airfare'=>true,'health'=>true,'dependents'=>true],
            'deadline'=>'2025-12-05','official_url'=>'https://www.gatescambridge.org'
        ]);
        $add('rhodes','Rhodes Scholarship',[
            'destination_countries'=>['UK'],'eligible_citizenships'=>['Pakistan','India','Bangladesh','South Africa','Any'],'citizen_rule'=>'list','degree_levels'=>['Masters'],
            'min_gpa'=>3.7,'age_max'=>25,'leadership_required'=>true,'deadline'=>'2025-10-01','official_url'=>'https://www.rhodeshouse.ox.ac.uk'
        ]);
        $add('erasmus_emjm','Erasmus Mundus Joint Master Degrees',[
            'sponsor'=>'European Union','destination_countries'=>['Europe'],'degree_levels'=>['Masters'],'min_gpa'=>3.3,'test_requirements'=>['IELTS','TOEFL'],'min_ielts'=>6.5,
            'coverage'=>['tuition'=>true,'stipend'=>true,'airfare'=>true,'health'=>true],'deadline'=>'2026-01-15','official_url'=>'https://erasmus-plus.ec.europa.eu',
            'selection_criteria'=>['merit','mobility','diversity'],'value_description'=>'Full tuition + monthly stipend + travel allowance'
        ]);
        $add('daad_epos','DAAD EPOS (Germany)',[
            'sponsor'=>'DAAD Germany','destination_countries'=>['Germany','Europe'],'eligible_citizenships'=>['Pakistan','India','Bangladesh','Nepal','Sri Lanka','Nigeria','Ghana','Kenya','Ethiopia','Uganda','Tanzania','South Africa','Egypt','Afghanistan','Philippines','Indonesia','Vietnam','Cambodia','Any'],
            'citizen_rule'=>'list','degree_levels'=>['Masters'],'test_requirements'=>['IELTS','TOEFL'],'experience_required'=>true,'min_work_years'=>2,'dev_focus'=>true,
            'coverage'=>['tuition'=>true,'stipend'=>true,'airfare'=>true,'health'=>true],'deadline'=>'2025-12-31','official_url'=>'https://www.daad.de'
        ]);
        $add('helmut_schmidt','DAAD Helmut-Schmidt (PPGG)',['destination_countries'=>['Germany'],'degree_levels'=>['Masters'],'test_requirements'=>['IELTS','TOEFL'],'public_policy'=>true,'deadline'=>'2025-07-31','official_url'=>'https://www.daad.de']);
        $add('sisgp','Swedish Institute Scholarships for Global Professionals',[
            'destination_countries'=>['Sweden'],'eligible_citizenships'=>['Pakistan','India','Bangladesh','Nepal','Sri Lanka','South Africa','Kenya','Ghana','Nigeria'],'citizen_rule'=>'list','degree_levels'=>['Masters'],
            'test_requirements'=>['IELTS','TOEFL'],'leadership_required'=>true,'deadline'=>'2026-02-28','official_url'=>'https://si.se'
        ]);
        $add('hk_pfs','Hong Kong PhD Fellowship Scheme',['destination_countries'=>['Hong Kong'],'degree_levels'=>['PhD'],'test_requirements'=>['IELTS','TOEFL'],'deadline'=>'2025-12-01','official_url'=>'https://cerg1.ugc.edu.hk']);
        $add('fulbright','Fulbright Foreign Student Program',[
            'sponsor'=>'U.S. Department of State','destination_countries'=>['USA'],'eligible_citizenships'=>['Pakistan','India','Bangladesh','Nepal','Sri Lanka','Nigeria','Ghana','Kenya','Ethiopia','South Africa','Egypt','Philippines','Indonesia','Vietnam','Any'],'citizen_rule'=>'list',
            'degree_levels'=>['Masters','PhD'],'min_gpa'=>3.2,'test_requirements'=>['IELTS','TOEFL','GRE'],'min_ielts'=>7.0,'min_toefl'=>90,
            'coverage'=>['tuition'=>true,'stipend'=>true,'airfare'=>true,'health'=>true],'bond_required'=>true,'bond_years'=>2,'deadline'=>'2025-10-15','official_url'=>'https://foreign.fulbrightonline.org',
            'docs_required'=>['lors'=>3,'sop'=>true,'passport'=>true,'medical'=>true,'english_cert'=>true],'selection_criteria'=>['merit','leadership','service commitment'],
            'value_description'=>'Full tuition + living stipend + health insurance + round-trip airfare'
        ]);
        $add('knight_hennessy','Knight-Hennessy Scholars (Stanford)',['destination_countries'=>['USA'],'degree_levels'=>['Masters','PhD'],'min_gpa'=>3.5,'leadership_required'=>true,'deadline'=>'2025-10-09','official_url'=>'https://knight-hennessy.stanford.edu']);
        $add('aauw','AAUW International Fellowships (USA)',['destination_countries'=>['USA'],'degree_levels'=>['Masters','PhD'],'citizen_rule'=>'women_nonUS','women_only'=>true,'min_gpa'=>3.0,'deadline'=>'2025-12-01','official_url'=>'https://www.aauw.org']);
        $add('vanier','Vanier Canada Graduate Scholarships',['destination_countries'=>['Canada'],'degree_levels'=>['PhD'],'min_gpa'=>3.5,'offer_required'=>true,'deadline'=>'2025-11-01','official_url'=>'https://vanier.gc.ca']);
        $add('australia_awards','Australia Awards Scholarships',[
            'destination_countries'=>['Australia'],'eligible_citizenships'=>['Pakistan','India','Bangladesh','Nepal','Sri Lanka','South Africa'],'citizen_rule'=>'list','degree_levels'=>['Undergraduate','Masters','PhD'],'min_gpa'=>3.0,
            'test_requirements'=>['IELTS','TOEFL'],'min_ielts'=>6.5,'experience_required'=>true,'min_work_years'=>2,'need_based'=>true,'bond_required'=>true,'bond_years'=>2,
            'coverage'=>['tuition'=>true,'stipend'=>true,'airfare'=>true,'health'=>true,'dependents'=>true],'deadline'=>'2025-11-30','official_url'=>'https://www.dfat.gov.au/people-to-people/australia-awards',
            'selection_criteria'=>['development impact','leadership','equity'],'value_description'=>'Full tuition + living allowance + health insurance + family support'
        ]);
        $add('manaaki_nz','Manaaki New Zealand Scholarships',['destination_countries'=>['New Zealand'],'eligible_citizenships'=>['Pakistan','India','Bangladesh','Nepal','Sri Lanka'],'citizen_rule'=>'list','degree_levels'=>['Undergraduate','Masters','PhD'],'test_requirements'=>['IELTS','TOEFL'],'bond_required'=>true,'deadline'=>'2026-03-31','official_url'=>'https://www.nzscholarships.govt.nz']);
        $add('mext','MEXT (Monbukagakusho)',['destination_countries'=>['Japan'],'degree_levels'=>['Undergraduate','Masters','PhD'],'deadline'=>'2025-06-01','official_url'=>'https://www.mext.go.jp']);
        $add('gks','Global Korea Scholarship (GKS)',['destination_countries'=>['South Korea','Korea'],'degree_levels'=>['Undergraduate','Masters','PhD'],'min_gpa'=>2.8,'coverage'=>['tuition'=>true,'stipend'=>true,'airfare'=>true,'health'=>true],'deadline'=>'2026-03-01','official_url'=>'https://www.studyinkorea.go.kr']);
        $add('schwarzman','Schwarzman Scholars (Tsinghua)',['destination_countries'=>['China'],'degree_levels'=>['Masters'],'leadership_required'=>true,'test_requirements'=>['IELTS','TOEFL'],'deadline'=>'2025-09-10','official_url'=>'https://www.schwarzmanscholars.org']);
        $add('yenching','Yenching Academy (PKU)',['destination_countries'=>['China'],'degree_levels'=>['Masters'],'leadership_required'=>true,'test_requirements'=>['IELTS','TOEFL'],'deadline'=>'2025-12-03','official_url'=>'https://yenchingacademy.pku.edu.cn']);
        $add('csc','Chinese Government Scholarship (CSC)',['sponsor'=>'China Scholarship Council','destination_countries'=>['China'],'degree_levels'=>['Undergraduate','Masters','PhD'],'min_gpa'=>2.5,'coverage'=>['tuition'=>true,'stipend'=>true,'airfare'=>true,'health'=>true],'deadline'=>'2026-03-31','official_url'=>'https://www.campuschina.org']);
        $add('kaust','KAUST Fellowship',['destination_countries'=>['Saudi Arabia'],'degree_levels'=>['Masters','PhD'],'min_gpa'=>3.0,'test_requirements'=>['IELTS','TOEFL'],'offer_required'=>true,'deadline'=>'2026-06-01','official_url'=>'https://www.kaust.edu.sa']);
        $add('hbku','Hamad Bin Khalifa University Scholarships',['destination_countries'=>['Qatar'],'degree_levels'=>['Masters','PhD'],'test_requirements'=>['IELTS','TOEFL'],'deadline'=>'2026-02-01','official_url'=>'https://www.hbku.edu.qa']);
        $add('qatar_uni','Qatar University Scholarships',['destination_countries'=>['Qatar'],'degree_levels'=>['Undergraduate','Masters','PhD'],'test_requirements'=>['IELTS','TOEFL'],'deadline'=>'2026-05-01','official_url'=>'https://www.qu.edu.qa']);
        $add('isdb','Islamic Development Bank (IsDB) Scholarship',['destination_countries'=>['Multiple'],'eligible_citizenships'=>['Pakistan','India','Bangladesh','Nepal','Egypt','Jordan','Morocco','Indonesia','Turkey','Saudi Arabia','UAE','Nigeria','Ghana'],'citizen_rule'=>'list','degree_levels'=>['Undergraduate','Masters','PhD','Postdoc'],'need_based'=>true,'deadline'=>'2026-02-15','official_url'=>'https://www.isdb.org']);
        $add('agakhan','Aga Khan Foundation International Scholarship',['destination_countries'=>['Multiple','Anywhere'],'eligible_citizenships'=>['Pakistan','India','Bangladesh','Afghanistan','Tajikistan','Kenya','Tanzania','Uganda','Mozambique','Egypt','Syria'],'citizen_rule'=>'list','degree_levels'=>['Masters','PhD'],'min_gpa'=>3.0,'need_based'=>true,'deadline'=>'2026-03-31','official_url'=>'https://the.akdn/en/resources-media/services/aga-khan-foundation-international-scholarship-programme','value_description'=>'50% grant + 50% interest-free loan']);
        $add('nrf','NRF South Africa Postgraduate Funding',['destination_countries'=>['South Africa'],'degree_levels'=>['Masters','PhD'],'deadline'=>'2025-08-31','official_url'=>'https://www.nrf.ac.za']);
        $add('pau','Pan African University Scholarships',['destination_countries'=>['Africa'],'degree_levels'=>['Masters','PhD'],'citizen_rule'=>'african_only','african_only'=>true,'deadline'=>'2025-09-15','official_url'=>'https://www.pau-au.africa']);
        $add('aims','AIMS Masters in Mathematical Sciences',['destination_countries'=>['South Africa','Rwanda','Senegal','Ghana','Cameroon'],'degree_levels'=>['Masters'],'math_focus'=>true,'fields_of_study'=>['Mathematics','Statistics','Data Science','Computer Science'],'deadline'=>'2026-03-31','official_url'=>'https://www.nexteinstein.org']);
        $add('eth_excellence','ETH Zurich Excellence Scholarship',['destination_countries'=>['Switzerland'],'degree_levels'=>['Masters'],'min_gpa'=>3.5,'deadline'=>'2025-12-15','official_url'=>'https://ethz.ch']);
        $add('epfl_excellence','EPFL Excellence Fellowships',['destination_countries'=>['Switzerland'],'degree_levels'=>['Masters'],'min_gpa'=>3.5,'deadline'=>'2025-12-15','official_url'=>'https://www.epfl.ch']);
        $add('singa','Singapore International Graduate Award (SINGA)',['destination_countries'=>['Singapore'],'degree_levels'=>['PhD'],'test_requirements'=>['IELTS','TOEFL'],'deadline'=>'2026-01-01','official_url'=>'https://www.a-star.edu.sg/singa']);
        $add('oist','OIST PhD Fellowship',['destination_countries'=>['Japan'],'degree_levels'=>['PhD'],'test_requirements'=>['IELTS','TOEFL'],'deadline'=>'2025-11-15','official_url'=>'https://admissions.oist.jp']);
        $add('melb_mgrs','Melbourne Graduate Research Scholarships',['destination_countries'=>['Australia'],'degree_levels'=>['Masters','PhD'],'offer_required'=>true,'deadline'=>'2026-10-31','official_url'=>'https://mdhs.unimelb.edu.au']);
        $add('monash_intl','Monash International Leadership Scholarship',['destination_countries'=>['Australia'],'degree_levels'=>['Undergraduate','Masters'],'min_gpa'=>3.3,'test_requirements'=>['IELTS','TOEFL'],'leadership_required'=>true,'deadline'=>'2026-06-30','official_url'=>'https://www.monash.edu']);
        $add('eiffel','Eiffel Excellence Scholarship Programme',['sponsor'=>'French Ministry for Europe','destination_countries'=>['France','Europe'],'degree_levels'=>['Masters','PhD'],'fields_of_study'=>['Engineering','Economics','Law','Business'],'min_gpa'=>3.5,'coverage'=>['tuition'=>false,'stipend'=>true,'health'=>true],'deadline'=>'2026-01-08','official_url'=>'https://www.campusfrance.org/en/eiffel-scholarship-program-of-excellence','value_description'=>'Monthly stipend + health insurance; tuition not covered by the Eiffel award itself']);
        $add('taiwan_icdf','Taiwan ICDF Scholarship',['destination_countries'=>['Taiwan','Asia'],'degree_levels'=>['Undergraduate','Masters'],'min_gpa'=>2.8,'coverage'=>['tuition'=>true,'stipend'=>true,'airfare'=>true,'health'=>true],'deadline'=>'2026-03-31','official_url'=>'https://www.icdf.org.tw']);
        $add('stipendium_hungaricum','Stipendium Hungaricum',['sponsor'=>'Hungarian Government','destination_countries'=>['Hungary','Europe'],'degree_levels'=>['Undergraduate','Masters','PhD'],'min_gpa'=>2.5,'coverage'=>['tuition'=>true,'stipend'=>true,'airfare'=>false,'health'=>true],'deadline'=>'2026-01-16','official_url'=>'https://stipendiumhungaricum.hu']);
        return $s;
    }

    private function crm(): Collection
    {
        $rows=collect();
        try{
            if($this->table('opportunities')) $rows=DB::table('opportunities')->limit(2000)->get();
        }catch(\Throwable $e){ report($e); }
        if($rows->isEmpty()) return collect();
        return $rows->map(fn($r)=>$this->normalizeCrm((array)$r))->filter(fn($x)=>$x!==null)->values();
    }

    private function normalizeCrm(array $r): ?array
    {
        if(!empty($r['deleted_at'])) return null;
        if(isset($r['status']) && !in_array(Str::lower((string)$r['status']),['published','active','open','approved','upcoming','live'],true)) return null;
        $meta=$this->json($r['metadata']??null); $flat=$r;
        foreach($this->flatten($meta) as $k=>$v) $flat['metadata_'.$k]=$v;
        $type=Str::lower((string)($this->pick($flat,[['type'],['opportunity','type'],['category'],['kind']])??''));
        $title=(string)($this->pick($flat,[['title'],['name']])??'');
        if(!Str::contains($type,'scholar') && !Str::contains(Str::lower($title),'scholar') && !Str::contains(Str::lower((string)($flat['description']??'')),'scholar')) return null;
        if($title==='') return null;
        $id=Str::slug((string)($r['slug']??$title));
        $country=$this->str($this->pick($flat,[['country','name'],['destination'],['country']]));
        return [
            'id'=>$id,'title'=>$title,'sponsor'=>$this->str($this->pick($flat,[['sponsor'],['organization'],['provider']])),
            'destination_countries'=>$country?[$country]:$this->list($this->pick($flat,[['destination','countries'],['destinations']])),
            'eligible_citizenships'=>$this->list($this->pick($flat,[['eligible','citizens'],['eligible','countries'],['citizenship'],['nationality']])),
            'citizen_rule'=>'list','degree_levels'=>$this->list($this->pick($flat,[['degree','levels'],['study','level'],['levels']])),
            'fields_of_study'=>$this->list($this->pick($flat,[['fields','study'],['field','study'],['subjects']])),
            'min_gpa'=>$this->num($this->pick($flat,[['minimum','gpa'],['min','gpa']])), 'gpa_scale'=>$this->num($this->pick($flat,[['gpa','scale']]))?:4.0,
            'test_requirements'=>$this->list($this->pick($flat,[['test','requirements'],['english','tests']])),
            'min_ielts'=>$this->num($this->pick($flat,[['minimum','ielts'],['ielts','score']])), 'min_toefl'=>$this->num($this->pick($flat,[['minimum','toefl'],['toefl','score']])),
            'experience_required'=>$this->bool($this->pick($flat,[['experience','required']])), 'min_work_years'=>$this->num($this->pick($flat,[['minimum','experience'],['work','years']]))?:0,
            'age_max'=>$this->num($this->pick($flat,[['maximum','age'],['age','limit']])), 'leadership_required'=>$this->bool($this->pick($flat,[['leadership','required']])),
            'need_based'=>$this->bool($this->pick($flat,[['need','based'],['financial','need']])), 'bond_required'=>$this->bool($this->pick($flat,[['bond','required'],['return','bond']])),
            'bond_years'=>$this->num($this->pick($flat,[['bond','years']]))?:0,'offer_required'=>$this->bool($this->pick($flat,[['offer','required'],['admission','offer']])),
            'supervisor_required'=>$this->bool($this->pick($flat,[['supervisor','required']])), 'research_required'=>$this->bool($this->pick($flat,[['research','required']])),
            'coverage'=>$this->json($this->pick($flat,[['coverage']])), 'deadline'=>$this->date($this->pick($flat,[['application','deadline'],['deadline'],['closing','date']])),
            'official_url'=>$this->str($this->pick($flat,[['application','url'],['official','url'],['external','url'],['website']])),
            'value_description'=>$this->str($this->pick($flat,[['funding'],['award'],['value','description'],['benefit']])),
            'selection_criteria'=>$this->list($this->pick($flat,[['selection','criteria'],['eligibility'],['requirements']])),
            'source'=>'crm','crm_slug'=>(string)($r['slug']??$id)
        ];
    }

    private function merge(array $base,array $crm): array
    {
        $out=$base;
        foreach($crm as $k=>$v){ if($v!==null && $v!=='' && $v!==[] && $v!==false) $out[$k]=$v; }
        $out['source']='crm+predefined';
        return $out;
    }
    private function canonicalId(array $x): string { $id=Str::slug((string)($x['id']??$x['title']??'scholarship')); foreach(['chevening','fulbright','erasmus','daad-epos','stipendium-hungaricum','gates-cambridge','australia-awards','gks','csc','eiffel'] as $known){ if(Str::contains($id,$known)) return $known==='erasmus'?'erasmus_emjm':($known==='daad-epos'?'daad_epos':str_replace('-','_',$known)); } return str_replace('-','_',$id); }
    private function table(string $t): bool { $x=DB::selectOne('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$t]); return (int)($x->c??0)>0; }
    private function json(mixed $v): array { if(is_array($v)) return $v; if(is_object($v)) return (array)$v; if(!is_string($v)||trim($v)==='') return []; $d=json_decode($v,true); return is_array($d)?$d:[]; }
    private function flatten(array $a,string $p=''): array { $o=[]; foreach($a as $k=>$v){$f=$p===''?(string)$k:$p.'_'.$k;if(is_array($v)){if(array_is_list($v))$o[$f]=implode(', ',array_filter(array_map('strval',$v)));else $o+=$this->flatten($v,$f);}elseif(is_scalar($v)&&trim((string)$v)!=='')$o[$f]=$v;}return $o; }
    private function pick(array $r,array $patterns): mixed { $pairs=[];foreach($r as $k=>$v){if($v===null||$v==='')continue;$pairs[]=[preg_replace('/[^a-z0-9]+/','',Str::lower((string)$k)),$v];}foreach($patterns as $p){$n=implode('',array_map(fn($x)=>preg_replace('/[^a-z0-9]+/','',Str::lower($x)),$p));foreach($pairs as [$k,$v])if($k===$n)return $v;}foreach($patterns as $p){$ns=array_map(fn($x)=>preg_replace('/[^a-z0-9]+/','',Str::lower($x)),$p);foreach($pairs as [$k,$v]){if($k==='id'||Str::endsWith($k,'id'))continue;$ok=true;foreach($ns as $n)if(!Str::contains($k,$n)){$ok=false;break;}if($ok)return $v;}}return null; }
    private function str(mixed $v): ?string { if($v===null||$v==='')return null;return trim(is_scalar($v)?(string)$v:json_encode($v)); }
    private function list(mixed $v): array { if($v===null||$v==='')return []; if(is_array($v))return array_values(array_filter(array_map(fn($x)=>trim((string)$x),collect($v)->flatten()->all())));$s=trim((string)$v);$j=json_decode($s,true);if(is_array($j))return $this->list($j);return array_values(array_filter(array_map('trim',preg_split('/[,;|\/]+/',$s)?:[]))); }
    private function num(mixed $v): ?float { if($v===null||$v==='')return null;if(is_numeric($v))return (float)$v;return preg_match('/-?\d+(?:\.\d+)?/',(string)$v,$m)?(float)$m[0]:null; }
    private function bool(mixed $v): bool { if(is_bool($v))return $v;if(is_numeric($v))return (int)$v===1;return in_array(Str::lower(trim((string)$v)),['yes','true','1','required','on'],true); }
    private function date(mixed $v): ?string { if(!$v)return null;try{return \Illuminate\Support\Carbon::parse((string)$v)->toDateString();}catch(\Throwable){return null;} }
}
''')

w(PUB,'app/Services/Tools/D2dEligibilityEngine.php',r'''
<?php
namespace App\Services\Tools;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class D2dEligibilityEngine
{
    private array $africa=['Nigeria','Ghana','Kenya','Ethiopia','Uganda','Tanzania','South Africa','Egypt','Rwanda','Senegal','Cameroon','Mozambique'];

    public function run(array $profile, iterable $items): array
    {
        $p=$this->profile($profile); $results=[];
        foreach($items as $s) $results[]=$this->one((array)$s,$p);
        usort($results,function($a,$b){$ord=['eligible'=>0,'needs_review'=>1,'not_eligible'=>2];return [$ord[$a['status']]??9,-$a['score'],$a['title']]<=>[$ord[$b['status']]??9,-$b['score'],$b['title']];});
        $summary=['total'=>count($results),'eligible'=>0,'needs_review'=>0,'not_eligible'=>0];
        foreach($results as $r)$summary[$r['status']]++;
        return ['profile'=>$p,'summary'=>$summary,'results'=>$results,'generated_at'=>now()->toIso8601String()];
    }

    private function one(array $s,array $p): array
    {
        $hard=[];$review=[];$pass=[];$fix=[];$checked=0;$points=0;$max=0;
        $check=function(bool $ok,string $yes,string $no,int $weight=12,bool $isHard=true) use (&$hard,&$review,&$pass,&$fix,&$checked,&$points,&$max){$checked++;$max+=$weight;if($ok){$pass[]=$yes;$points+=$weight;}else{if($isHard)$hard[]=$no;else$review[]=$no;$fix[]=$no;}};

        $rule=$s['citizen_rule']??'any'; $elig=$s['eligible_citizenships']??['Any'];
        if($rule!=='any' || !in_array('Any',$elig,true)){
            $ok=true;
            if($rule==='african_only'||!empty($s['african_only']))$ok=in_array($p['citizenship'],$this->africa,true);
            elseif($rule==='women_nonUS'||!empty($s['women_only']))$ok=$p['gender']==='Female' && !in_array($p['citizenship'],['USA','United States'],true);
            else $ok=in_array('Any',$elig,true)||collect($elig)->contains(fn($x)=>Str::lower((string)$x)===Str::lower($p['citizenship']));
            $check($ok,'Citizenship requirement matches.','Citizenship does not match the published eligibility list.',16,true);
        }
        if(!empty($s['degree_levels'])){
            $ok=collect($s['degree_levels'])->contains(fn($x)=>Str::lower((string)$x)===Str::lower($p['target_level']));
            $check($ok,'Target degree is supported.','Target degree is not supported by this scholarship.',16,true);
        }
        if(($s['min_gpa']??null)!==null){$req=$this->gpa4((float)$s['min_gpa'],(float)($s['gpa_scale']??4));if($p['gpa4']===null){$review[]='GPA is required but you did not provide one.';$fix[]='Add your GPA to confirm this requirement.';$max+=14;}else$check($p['gpa4']+0.0001>=$req,'GPA meets the published minimum.','GPA is below the published minimum.',14,true);}
        if(($s['age_max']??null)!==null){if($p['age']===null){$review[]='Age limit exists; add date of birth to confirm.';$max+=10;}else$check($p['age']<=(int)$s['age_max'],'Age requirement matches.','You are above the published age limit.',10,true);}
        if(!empty($s['experience_required'])||((float)($s['min_work_years']??0)>0)) $check($p['experience']>=(float)($s['min_work_years']??0),'Work-experience requirement matches.','More work experience is required.',12,true);
        if(!empty($s['fields_of_study'])&&!in_array('Any',$s['fields_of_study'],true)&&$p['field']!==''){
            $ok=collect($s['fields_of_study'])->contains(fn($x)=>Str::contains(Str::lower((string)$x),Str::lower($p['field']))||Str::contains(Str::lower($p['field']),Str::lower((string)$x)));
            $check($ok,'Field of study appears aligned.','Field of study should be manually checked against the published subject list.',8,false);
        }
        if(!empty($s['math_focus'])&&$p['field']!==''){$ok=Str::contains(Str::lower($p['field']),['math','stat','data','computer']);$check($ok,'Field fits the mathematics focus.','This scholarship has a mathematics-focused subject requirement.',8,false);}
        $tests=$s['test_requirements']??[];
        if($tests){$has=false;foreach($tests as $t){$u=Str::upper((string)$t);if($u==='IELTS'&&$p['ielts']!==null&&(($s['min_ielts']??null)===null||$p['ielts']>=(float)$s['min_ielts']))$has=true;if($u==='TOEFL'&&$p['toefl']!==null&&(($s['min_toefl']??null)===null||$p['toefl']>=(float)$s['min_toefl']))$has=true;if(in_array($u,$p['tests'],true))$has=true;}$check($has,'A listed test requirement appears satisfied.','Language/test requirement needs confirmation or a qualifying score.',10,false);}
        if(!empty($s['leadership_required']))$check($p['leadership'],'Leadership evidence indicated.','Leadership evidence appears important for this scholarship.',8,false);
        if(!empty($s['need_based']))$check($p['financial_need'],'Financial-need profile indicated.','Financial-need evidence may be required.',6,false);
        if(!empty($s['bond_required']))$check($p['accept_bond'],'Return/service bond accepted.','This scholarship includes a return/service bond that conflicts with your preference.',8,true);
        if(!empty($s['offer_required']))$check($p['has_offer'],'Admission offer indicated.','Admission offer may be required before scholarship consideration.',8,false);
        if(!empty($s['supervisor_required']))$check($p['has_supervisor'],'Supervisor readiness indicated.','A supervisor may be required.',7,false);
        if(!empty($s['research_required']))$check($p['research'],'Research readiness indicated.','Research experience/proposal may be required.',7,false);
        if(!empty($s['public_policy']))$check(Str::contains(Str::lower($p['field']),['policy','govern','public','development']),'Field appears aligned with public-policy focus.','Program is public-policy/governance focused.',6,false);
        if(!empty($s['dev_focus']))$check($p['development_focus'],'Development-impact focus indicated.','Development-related professional impact should be demonstrated.',6,false);

        if($checked===0){$review[]='Not enough structured eligibility criteria are stored for an automatic decision.';$fix[]='Review the official scholarship criteria.';}
        $score=$max>0?(int)round(($points/$max)*100):50;
        if($hard)$status='not_eligible'; elseif($review)$status='needs_review'; else $status='eligible';
        $deadline=$s['deadline']??null;$cycle='unknown';
        if($deadline){try{$d=Carbon::parse($deadline)->endOfDay();$cycle=$d->isPast()?'past_cycle':'upcoming';}catch(\Throwable){}}
        $local=$s['crm_slug']?('/scholarships/'.ltrim((string)$s['crm_slug'],'/')):null;
        return array_merge($s,['status'=>$status,'score'=>$score,'criteria_checked'=>$checked,'pass_reasons'=>$pass,'review_reasons'=>$review,'fail_reasons'=>$hard,'improve_actions'=>array_values(array_unique($fix)),'deadline_status'=>$cycle,'local_url'=>$local]);
    }

    private function profile(array $p): array
    {
        $age=null;if(!empty($p['dob']))try{$age=Carbon::parse($p['dob'])->age;}catch(\Throwable){}
        $gpa=isset($p['gpa'])&&$p['gpa']!==''?(float)$p['gpa']:null;$scale=(string)($p['gpa_scale']??'4.0');$g4=$gpa===null?null:match($scale){'10.0'=>($gpa/10)*4,'Percentage'=>($gpa/100)*4,default=>$gpa};
        return [
            'citizenship'=>trim((string)($p['citizenship']??'')),'gender'=>(string)($p['gender']??''),'dob'=>$p['dob']??null,'age'=>$age,
            'experience'=>(float)($p['experience']??0),'current_level'=>(string)($p['current_level']??''),'target_level'=>(string)($p['target_level']??''),'field'=>trim((string)($p['field']??'')),
            'gpa'=>$gpa,'gpa_scale'=>$scale,'gpa4'=>$g4,'ielts'=>isset($p['ielts'])&&$p['ielts']!==''?(float)$p['ielts']:null,'toefl'=>isset($p['toefl'])&&$p['toefl']!==''?(float)$p['toefl']:null,
            'tests'=>array_map('strtoupper',(array)($p['tests']??[])),'leadership'=>(bool)($p['leadership']??false),'community_service'=>(bool)($p['community_service']??false),'financial_need'=>(bool)($p['financial_need']??false),
            'accept_bond'=>(bool)($p['accept_bond']??false),'has_offer'=>(bool)($p['has_offer']??false),'has_supervisor'=>(bool)($p['has_supervisor']??false),'research'=>(bool)($p['research']??false),'development_focus'=>(bool)($p['development_focus']??false)
        ];
    }
    private function gpa4(float $v,float $scale): float{return $scale>0&&abs($scale-4)>0.01?($v/$scale)*4:$v;}
}
''')

w(PUB,'app/Http/Controllers/Tools/D2dEligibilityV3Controller.php',r'''
<?php
namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\Tools\D2dEligibilityEngine;
use App\Services\Tools\D2dScholarshipCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class D2dEligibilityV3Controller extends Controller
{
    public function index(D2dScholarshipCatalog $catalog){return view('tools.eligibility-v3',['catalogCount'=>$catalog->all()->count()]);}
    public function check(Request $r,D2dScholarshipCatalog $catalog,D2dEligibilityEngine $engine){$p=$r->validate([
        'citizenship'=>'required|string|max:120','gender'=>'nullable|string|max:20','dob'=>'nullable|date','experience'=>'nullable|numeric|min:0|max:80','current_level'=>'nullable|string|max:80','target_level'=>'required|string|max:80','field'=>'nullable|string|max:160','gpa'=>'nullable|numeric|min:0|max:100','gpa_scale'=>'nullable|string|max:20','ielts'=>'nullable|numeric|min:0|max:9','toefl'=>'nullable|numeric|min:0|max:120','tests'=>'nullable|array','tests.*'=>'string|max:30','leadership'=>'nullable|boolean','community_service'=>'nullable|boolean','financial_need'=>'nullable|boolean','accept_bond'=>'nullable|boolean','has_offer'=>'nullable|boolean','has_supervisor'=>'nullable|boolean','research'=>'nullable|boolean','development_focus'=>'nullable|boolean']);
        $data=$engine->run($p,$catalog->all());$token=Str::random(48);session()->put('d2d_elig_v3_'.$token,['input'=>$p,'result'=>$data,'at'=>now()->timestamp]);return response()->json(['ok'=>true,'run_token'=>$token,'data'=>$data]);}
    public function save(Request $r){$v=$r->validate(['run_token'=>'required|string|size:48']);$run=session()->get('d2d_elig_v3_'.$v['run_token']);abort_unless(is_array($run),422,'This result expired. Run the checker again.');
        $exists=DB::selectOne('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['d2d_student_tool_results']);abort_unless((int)($exists->c??0)>0,503,'Portal Saved Results is not installed yet. Install the Portal part of this package first.');
        $claim=Str::random(56);$public=(string)Str::uuid();DB::table('d2d_student_tool_results')->insert(['public_id'=>$public,'user_id'=>null,'tool_key'=>'eligibility-checker','tool_version'=>'3.0','title'=>'Scholarship Eligibility Check','input_json'=>json_encode($run['input'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'result_json'=>json_encode($run['result'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'summary_json'=>json_encode($run['result']['summary']??[],JSON_UNESCAPED_UNICODE),'claim_token'=>$claim,'share_token'=>null,'is_shared'=>0,'claimed_at'=>null,'created_at'=>now(),'updated_at'=>now()]);session()->forget('d2d_elig_v3_'.$v['run_token']);return response()->json(['ok'=>true,'redirect'=>'https://portal.dares2dream.com/tool-results/claim/'.$claim]);}
    public function share(string $token){$row=DB::table('d2d_student_tool_results')->where('share_token',$token)->where('is_shared',1)->first();abort_unless($row,404);$p=json_decode((string)$row->input_json,true)?:[];unset($p['dob']);return view('tools.eligibility-shared-v3',['row'=>$row,'profile'=>$p,'result'=>json_decode((string)$row->result_json,true)?:[]]);}
}
''')

w(PUB,'resources/views/tools/eligibility-v3.blade.php',r'''
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Scholarship Eligibility Checker — Dare To Dream</title><link rel="stylesheet" href="/v11/styles.css"><link rel="stylesheet" href="/d2d-tools/eligibility-v3.css?v=3"></head>
<body class="ec"><header class="ec-head"><a href="/" class="brand">DARE TO DREAM<small>DREAM BIG. STUDY ABROAD.</small></a><nav><a href="/scholarships">Scholarships</a><a href="/universities">Universities</a><a href="/opportunities">Opportunities</a><a href="/jobs">Jobs Abroad</a><a href="/internships">Internships</a><a class="active" href="/tools">Tools</a><a href="/blog">Blog</a></nav><a class="login" href="https://portal.dares2dream.com/">Log in</a></header>
<section class="ec-hero"><div><span>D2D TOOL #01 · {{ $catalogCount }} SCHOLARSHIPS LOADED</span><h1>CHECK YOUR <em>ELIGIBILITY.</em></h1><p>One profile. Every scholarship in the D2D master catalogue plus current CRM scholarship records. See what matches, what needs review, and what does not.</p></div><aside><b>{{ $catalogCount }}</b><small>scholarships checked in one run</small></aside></section>
<main class="ec-shell"><div class="ec-grid"><aside class="ec-side"><button class="on" data-go="1"><b>01</b><span>Profile<small>Identity & experience</small></span></button><button data-go="2"><b>02</b><span>Academics<small>Degree, field & GPA</small></span></button><button data-go="3"><b>03</b><span>Readiness<small>Tests & strengths</small></span></button><button data-go="4"><b>04</b><span>Results<small>All scholarship outcomes</small></span></button><div class="side-note"><span>PORTAL CONNECTED</span><strong>Save results to your existing student account.</strong><a href="https://portal.dares2dream.com/saved-results">Saved Results →</a></div></aside>
<section class="ec-card"><div class="progress"><i id="bar"></i></div><form id="ecForm">
<section class="step show" data-step="1"><header><span>STEP 01</span><h2>Build your profile.</h2><p>We use these values only to test published eligibility rules.</p></header><div class="two"><label>Citizenship<input name="citizenship" required placeholder="e.g. Pakistan"></label><label>Gender<select name="gender"><option value="">Prefer not to say</option><option>Female</option><option>Male</option><option>Other</option></select></label></div><div class="two"><label>Date of birth<input name="dob" type="date"></label><label>Work experience (years)<input name="experience" type="number" min="0" step="0.1" placeholder="2"></label></div><label>Highest completed degree<select name="current_level"><option>High School</option><option>Undergraduate</option><option>Masters</option><option>PhD</option></select></label><footer><i></i><button type="button" data-next="2">Academic details →</button></footer></section>
<section class="step" data-step="2"><header><span>STEP 02</span><h2>Academic direction.</h2><p>Enter your target degree, field and academic standing.</p></header><div class="two"><label>Target degree<select name="target_level" required><option>Undergraduate</option><option selected>Masters</option><option>PhD</option><option>Postdoc</option></select></label><label>Field of study<input name="field" placeholder="e.g. Computer Science"></label></div><div class="three"><label>GPA / marks<input name="gpa" type="number" step="0.01" min="0" placeholder="3.4"></label><label>Scale<select name="gpa_scale"><option>4.0</option><option>10.0</option><option>Percentage</option></select></label><span class="info">If a scholarship has no stored GPA rule, D2D will not invent one.</span></div><footer><button class="back" type="button" data-back="1">← Back</button><button type="button" data-next="3">Readiness →</button></footer></section>
<section class="step" data-step="3"><header><span>STEP 03</span><h2>Readiness & constraints.</h2><p>Add actual scores where available. This makes results much more useful than simple yes/no chips.</p></header><div class="two"><label>IELTS score<input name="ielts" type="number" min="0" max="9" step="0.5" placeholder="6.5"></label><label>TOEFL iBT<input name="toefl" type="number" min="0" max="120" step="1" placeholder="90"></label></div><div class="choices" data-group="tests"><button type="button" data-v="GRE">GRE ready</button><button type="button" data-v="GMAT">GMAT ready</button></div><h3>Profile signals</h3><div class="toggles"><label><input type="checkbox" name="leadership">Leadership evidence</label><label><input type="checkbox" name="community_service">Community service</label><label><input type="checkbox" name="financial_need">Financial-need profile</label><label><input type="checkbox" name="research">Research readiness</label><label><input type="checkbox" name="development_focus">Development impact focus</label><label><input type="checkbox" name="has_offer">Admission offer ready</label><label><input type="checkbox" name="has_supervisor">Supervisor ready</label><label><input type="checkbox" name="accept_bond">I accept return/service bond schemes</label></div><footer><button class="back" type="button" data-back="2">← Back</button><button class="run" type="submit">Check all scholarships →</button></footer></section>
<section class="step" data-step="4"><header><span>STEP 04</span><h2>Your complete eligibility map.</h2><p>All scholarships are shown. Past-cycle deadlines stay visible but are clearly marked for official re-checking.</p></header><div id="summary" class="loading">Run the checker to see results.</div><div class="filters"><button type="button" class="on" data-filter="all">All</button><button type="button" data-filter="eligible">Eligible</button><button type="button" data-filter="needs_review">Needs review</button><button type="button" data-filter="not_eligible">Not eligible</button></div><div id="results"></div><div id="saveBox" class="save" hidden><div><span>SAVE TO PORTAL</span><strong>Keep this result in your existing D2D student account.</strong><p>No separate public-site account is created.</p></div><button id="saveBtn" type="button">Save to my account →</button></div><footer><button class="back" type="button" data-back="3">← Edit profile</button><button type="button" class="back" data-next="1">Start over</button></footer></section>
</form></section></div></main><script src="/d2d-tools/eligibility-v3.js?v=3" defer></script></body></html>
''')

w(PUB,'resources/views/tools/eligibility-shared-v3.blade.php',r'''
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Shared D2D Eligibility Result</title><link rel="stylesheet" href="/d2d-tools/eligibility-v3.css?v=3"></head><body class="ec shared"><main class="share-card"><span>D2D SHARED RESULT</span><h1>Scholarship Eligibility Check</h1>@php($s=$result['summary']??[])<div class="share-stats"><b>{{ $s['eligible']??0 }}<small>Eligible</small></b><b>{{ $s['needs_review']??0 }}<small>Needs review</small></b><b>{{ $s['not_eligible']??0 }}<small>Not eligible</small></b></div><p>Citizenship: {{ $profile['citizenship']??'—' }} · Target: {{ $profile['target_level']??'—' }} · Field: {{ $profile['field']??'—' }}</p>@foreach(array_slice($result['results']??[],0,20) as $x)<article class="share-row"><strong>{{ $x['title']??'Scholarship' }}</strong><span class="pill {{ $x['status']??'' }}">{{ str_replace('_',' ',ucfirst($x['status']??'')) }}</span></article>@endforeach</main></body></html>
''')

w(PUB,'public/d2d-tools/eligibility-v3.css',r'''
:root{--gold:#f4af00;--black:#0d0e10;--ink:#15161a;--muted:#74767d;--line:#d9dade;--bg:#f4f4f1}*{box-sizing:border-box}.ec{margin:0;background:var(--bg);color:var(--ink);font-family:Manrope,Inter,Arial,sans-serif}.ec-head{height:88px;background:#fff;display:flex;align-items:center;gap:28px;padding:0 max(26px,calc((100vw - 1440px)/2));border-bottom:1px solid #eee}.brand{font:700 26px Oswald,Impact,sans-serif;color:#111;text-decoration:none;white-space:nowrap}.brand small{display:block;margin-top:6px;font:700 7px Manrope,sans-serif;color:#999;letter-spacing:.2em}.ec-head nav{display:flex;gap:22px;margin:auto}.ec-head nav a,.login{font-size:10px;font-weight:650;color:#111;text-decoration:none}.ec-head nav .active{border-bottom:2px solid var(--gold);padding-bottom:5px}.ec-hero{min-height:335px;background:linear-gradient(120deg,#111,#070809);color:#fff;padding:70px max(28px,calc((100vw - 1320px)/2));display:flex;justify-content:space-between;align-items:flex-end;gap:30px}.ec-hero>div{max-width:880px}.ec-hero span,.step header span,.side-note span,.save span,.share-card>span{color:var(--gold);font-size:9px;font-weight:800;letter-spacing:.15em}.ec-hero h1{margin:13px 0;font:700 clamp(55px,7vw,94px)/.92 Oswald,Impact,sans-serif}.ec-hero h1 em{font-style:normal;color:var(--gold)}.ec-hero p{max-width:820px;color:#c3c5ca;font-size:13px;line-height:1.65}.ec-hero aside{width:190px;border:1px solid #333;border-radius:14px;padding:20px}.ec-hero aside b{display:block;color:var(--gold);font-size:40px}.ec-hero aside small{color:#aaa}.ec-shell{width:min(1320px,calc(100% - 38px));margin:42px auto 80px}.ec-grid{display:grid;grid-template-columns:265px 1fr;gap:20px}.ec-side{background:#111216;border-radius:16px;padding:16px;position:sticky;top:18px}.ec-side>button{width:100%;display:flex;gap:12px;align-items:center;text-align:left;border:0;border-top:1px solid #292a2e;background:none;color:#fff;padding:15px 7px;cursor:pointer}.ec-side button b{width:32px;height:32px;border-radius:8px;background:#25262a;color:#999;display:grid;place-items:center;font-size:9px}.ec-side button span{font-size:10px;font-weight:700}.ec-side button small{display:block;margin-top:3px;color:#777;font-size:8px}.ec-side button.on b{background:var(--gold);color:#111}.ec-side button.on span{color:var(--gold)}.side-note{margin-top:18px;border-radius:10px;padding:15px;background:#1e1b09}.side-note strong{display:block;margin:7px 0;color:#fff;font-size:10px;line-height:1.4}.side-note a{color:#fff;font-size:8px}.ec-card{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden}.progress{height:4px;background:#eee}.progress i{display:block;height:100%;width:25%;background:var(--gold);transition:.2s}.step{display:none;padding:30px}.step.show{display:block}.step header{padding-bottom:23px;margin-bottom:23px;border-bottom:1px solid #eee}.step header h2{margin:7px 0;font-size:30px}.step header p{margin:0;color:var(--muted);font-size:10px}.step label{display:block;margin-bottom:16px;font-size:10px;font-weight:750}.step input,.step select{width:100%;height:49px;margin-top:7px;border:1px solid #cfd1d5;border-radius:9px;padding:0 12px;background:#fff;font:inherit}.two{display:grid;grid-template-columns:1fr 1fr;gap:13px}.three{display:grid;grid-template-columns:1.2fr .8fr 1.4fr;gap:13px;align-items:end}.info{min-height:49px;border-radius:9px;background:#fff7d8;border:1px solid #ecd276;padding:12px;font-size:8px;line-height:1.5;color:#665a38}.choices{display:flex;gap:8px;margin-bottom:20px}.choices button,.filters button{border:1px solid #d0d1d5;border-radius:999px;background:#fff;padding:8px 12px;font-size:9px;font-weight:700}.choices button.on,.filters button.on{background:#111;color:#fff}.step h3{font-size:11px;margin-top:22px}.toggles{display:grid;grid-template-columns:1fr 1fr;gap:8px}.toggles label{margin:0;border:1px solid #ddd;border-radius:9px;padding:12px;font-size:9px}.toggles input{width:auto;height:auto;margin:0 7px 0 0}.step footer{display:flex;justify-content:space-between;gap:10px;margin-top:28px;padding-top:20px;border-top:1px solid #eee}.step footer button,.save button{min-height:43px;border-radius:8px;padding:0 15px;border:1px solid var(--gold);background:var(--gold);color:#111;font-size:9px;font-weight:800}.step footer .back{background:#fff;border-color:#d0d1d5}.loading{padding:35px;border:1px dashed #ccc;border-radius:10px;text-align:center;color:#777}.dash{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px}.dash div{border:1px solid #ddd;border-radius:10px;padding:15px;text-align:center}.dash div:first-child{background:#111;color:#fff}.dash strong{display:block;font-size:25px}.dash span{font-size:8px;color:#888}.filters{display:flex;gap:7px;margin:13px 0 5px}.res{padding:17px 0;border-bottom:1px solid #eee}.res-head{display:flex;justify-content:space-between;gap:15px}.res h3{margin:0;font-size:14px}.res-meta{margin-top:4px;color:#888;font-size:8px}.score{width:45px;height:45px;border-radius:50%;background:#f3f3f1;display:grid;place-items:center;font-size:10px;font-weight:800}.pill{display:inline-block;margin-top:8px;border-radius:999px;padding:5px 8px;font-size:8px;font-weight:800}.pill.eligible{background:#111;color:#fff}.pill.needs_review{background:#fff0bd;color:#755300}.pill.not_eligible{background:#eee;color:#555}.reason{margin-top:7px;color:#606269;font-size:9px;line-height:1.5}.past{color:#9b6900;font-weight:700}.res a{display:inline-block;margin-top:8px;color:#111;font-size:9px;font-weight:800}.save{margin-top:22px;padding:17px;border:1px solid #e3c252;border-radius:11px;background:#fff8dd;display:flex;justify-content:space-between;align-items:center;gap:20px}.save strong{display:block;margin:5px 0;font-size:12px}.save p{margin:0;font-size:8px;color:#766d55}.share-card{max-width:900px;margin:50px auto;background:#fff;border:1px solid #ddd;border-radius:16px;padding:30px}.share-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:20px 0}.share-stats b{border:1px solid #ddd;border-radius:9px;padding:14px;text-align:center;font-size:22px}.share-stats small{display:block;font-size:8px;color:#888}.share-row{display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding:12px 0}
@media(max-width:1000px){.ec-head nav{display:none}.ec-grid{grid-template-columns:1fr}.ec-side{position:static;display:grid;grid-template-columns:repeat(4,1fr)}.side-note{grid-column:1/-1}.ec-hero aside{display:none}}@media(max-width:700px){.two,.three,.toggles,.dash{grid-template-columns:1fr}.ec-shell{width:calc(100% - 22px)}.ec-side{grid-template-columns:1fr 1fr}.step{padding:20px}.save{align-items:flex-start;flex-direction:column}.ec-hero{padding:50px 20px}}
''')

w(PUB,'public/d2d-tools/eligibility-v3.js',r'''
(()=>{const f=document.querySelector('#ecForm');if(!f)return;const csrf=document.querySelector('meta[name=csrf-token]').content;let run=null,data=null,filter='all';const qs=s=>document.querySelector(s),qsa=s=>[...document.querySelectorAll(s)];function go(n){qsa('[data-step]').forEach(x=>x.classList.toggle('show',+x.dataset.step===+n));qsa('[data-go]').forEach(x=>x.classList.toggle('on',+x.dataset.go===+n));qs('#bar').style.width=(n*25)+'%';scrollTo({top:qs('.ec-shell').offsetTop-15,behavior:'smooth'})}qsa('[data-go]').forEach(b=>b.onclick=()=>go(b.dataset.go));qsa('[data-next]').forEach(b=>b.onclick=()=>go(b.dataset.next));qsa('[data-back]').forEach(b=>b.onclick=()=>go(b.dataset.back));qsa('[data-group] button').forEach(b=>b.onclick=()=>b.classList.toggle('on'));const bool=n=>!!f.elements[n]?.checked;const val=n=>f.elements[n]?.value??'';const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));function payload(){return{citizenship:val('citizenship'),gender:val('gender'),dob:val('dob')||null,experience:val('experience')||0,current_level:val('current_level'),target_level:val('target_level'),field:val('field'),gpa:val('gpa')||null,gpa_scale:val('gpa_scale'),ielts:val('ielts')||null,toefl:val('toefl')||null,tests:qsa('[data-group=tests] button.on').map(x=>x.dataset.v),leadership:bool('leadership'),community_service:bool('community_service'),financial_need:bool('financial_need'),accept_bond:bool('accept_bond'),has_offer:bool('has_offer'),has_supervisor:bool('has_supervisor'),research:bool('research'),development_focus:bool('development_focus')}}function render(){if(!data)return;const s=data.summary||{};qs('#summary').className='';qs('#summary').innerHTML=`<div class="dash"><div><strong>${s.total||0}</strong><span>CHECKED</span></div><div><strong>${s.eligible||0}</strong><span>ELIGIBLE</span></div><div><strong>${s.needs_review||0}</strong><span>NEEDS REVIEW</span></div><div><strong>${s.not_eligible||0}</strong><span>NOT ELIGIBLE</span></div></div>`;const arr=(data.results||[]).filter(x=>filter==='all'||x.status===filter);qs('#results').innerHTML=arr.map(x=>{const bad=[...(x.fail_reasons||[]),...(x.review_reasons||[])];const good=x.pass_reasons||[];const note=bad[0]||good[0]||'Review official criteria.';const cycle=x.deadline_status==='past_cycle'?`<span class="past">Past stored cycle — verify current deadline</span>`:(x.deadline?`Deadline ${esc(x.deadline)}`:'Deadline: verify official site');const href=x.local_url||x.official_url||'#';return `<article class="res"><div class="res-head"><div><h3>${esc(x.title)}</h3><div class="res-meta">${esc((x.destination_countries||[]).join(', ')||'Global')} · ${cycle}</div></div><div class="score">${x.score}%</div></div><span class="pill ${esc(x.status)}">${esc(x.status.replace('_',' '))}</span><div class="reason">${esc(note)}</div><a href="${esc(href)}" target="_blank">Review scholarship →</a></article>`}).join('')||'<div class="loading">No results in this filter.</div>';qs('#saveBox').hidden=!(data.results||[]).length}qsa('[data-filter]').forEach(b=>b.onclick=()=>{filter=b.dataset.filter;qsa('[data-filter]').forEach(x=>x.classList.toggle('on',x===b));render()});f.onsubmit=async e=>{e.preventDefault();if(!val('citizenship')){go(1);f.elements.citizenship.focus();return}go(4);qs('#summary').className='loading';qs('#summary').textContent='Checking the entire D2D scholarship catalogue…';qs('#results').innerHTML='';qs('#saveBox').hidden=true;const r=await fetch('/tools/eligibility-checker/check',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload())});const j=await r.json();if(!r.ok){qs('#summary').textContent=j.message||'Could not run checker.';return}run=j.run_token;data=j.data;filter='all';render()};qs('#saveBtn').onclick=async()=>{if(!run)return;const b=qs('#saveBtn'),old=b.textContent;b.disabled=true;b.textContent='Opening Portal…';try{const r=await fetch('/tools/eligibility-checker/save',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({run_token:run})});const j=await r.json();if(!r.ok)throw Error(j.message||'Could not save');location.href=j.redirect}catch(e){alert(e.message);b.disabled=false;b.textContent=old}};go(1)})();
''')

w(PUB,'routes/d2d-eligibility-v3-console.php',r'''
<?php
use Illuminate\Support\Facades\Artisan; use Illuminate\Support\Facades\DB; use App\Services\Tools\D2dScholarshipCatalog;
Artisan::command('d2d:eligibility-v3-doctor',function(D2dScholarshipCatalog $c){$this->info('D2D Eligibility V3 Doctor');$this->line('Master catalogue: '.$c->all()->count());$this->line('Predefined: '.count($c->predefined()));$x=DB::selectOne('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['d2d_student_tool_results']);$this->line('Portal Saved Results table: '.((int)($x->c??0)>0?'OK':'MISSING — install Portal part first'));$this->line('No destructive migrations.');});
''')

# ---------------- PORTAL APP ----------------
w(PORTAL,'app/Providers/D2dPortalSavedResultsV3ServiceProvider.php',r'''
<?php
namespace App\Providers;
use App\Http\Controllers\D2dPortalSavedResultsV3Controller; use Illuminate\Support\Facades\Route; use Illuminate\Support\ServiceProvider;
class D2dPortalSavedResultsV3ServiceProvider extends ServiceProvider { public function register():void{} public function boot():void{ Route::middleware(['web','auth'])->group(function(){Route::get('/tool-results/claim/{token}',[D2dPortalSavedResultsV3Controller::class,'claim'])->where('token','[A-Za-z0-9]{40,80}');Route::get('/saved-results',[D2dPortalSavedResultsV3Controller::class,'index']);Route::get('/saved-results/{id}',[D2dPortalSavedResultsV3Controller::class,'show']);Route::post('/saved-results/{id}/share',[D2dPortalSavedResultsV3Controller::class,'share']);Route::post('/saved-results/{id}/share/revoke',[D2dPortalSavedResultsV3Controller::class,'revoke']);Route::get('/saved-results/{id}/pdf',[D2dPortalSavedResultsV3Controller::class,'pdf']);Route::delete('/saved-results/{id}',[D2dPortalSavedResultsV3Controller::class,'destroy']);}); if($this->app->runningInConsole())require base_path('routes/d2d-portal-results-v3-console.php');}}
''')

w(PORTAL,'app/Services/D2dPortalProfileSyncV3.php',r'''
<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class D2dPortalProfileSyncV3 {
 public function fillMissing(mixed $userId,array $p): array{$log=[];foreach(['student_profiles','student_profile','profiles'] as $t){if(!$this->table($t))continue;$cols=$this->cols($t);$fk=in_array('user_id',$cols,true)?'user_id':(in_array('student_id',$cols,true)?'student_id':null);if(!$fk)continue;$row=DB::table($t)->where($fk,$userId)->first();if(!$row)continue;$a=(array)$row;$map=['citizenship'=>['citizenship','nationality','country_of_citizenship'],'dob'=>['date_of_birth','dob','birth_date'],'target_level'=>['target_level','target_degree','study_level','degree_level'],'field'=>['field_of_study','study_field','major'],'gpa'=>['gpa','current_gpa'],'gpa_scale'=>['gpa_scale'],'experience'=>['work_experience_years','experience_years'],'ielts'=>['ielts_score','ielts'],'toefl'=>['toefl_score','toefl']];$up=[];foreach($map as $src=>$targets){$v=$p[$src]??null;if($v===null||$v==='')continue;foreach($targets as $col){if(in_array($col,$cols,true)&&(!isset($a[$col])||$a[$col]===null||$a[$col]==='')){$up[$col]=is_array($v)?json_encode($v):$v;$log[]=$t.'.'.$col;break;}}}if($up){if(in_array('updated_at',$cols,true))$up['updated_at']=now();DB::table($t)->where($fk,$userId)->update($up);}break;}return $log;}
 private function table($t){$x=DB::selectOne('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$t]);return(int)($x->c??0)>0;} private function cols($t){return array_map(fn($x)=>(string)$x->COLUMN_NAME,DB::select('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?',[$t]));}
}
''')

w(PORTAL,'app/Services/D2dResultPdfV3.php',r'''
<?php
namespace App\Services;
class D2dResultPdfV3 { public function download(object $row){$r=json_decode((string)$row->result_json,true)?:[];$p=json_decode((string)$row->input_json,true)?:[];$lines=['DARE TO DREAM','SCHOLARSHIP ELIGIBILITY REPORT','Result ID: '.$row->public_id,'Generated: '.date('F j, Y',strtotime((string)$row->created_at)),'','PROFILE','Citizenship: '.($p['citizenship']??'-'),'Target degree: '.($p['target_level']??'-'),'Field: '.($p['field']??'-'),'GPA: '.($p['gpa']??'-').' '.($p['gpa_scale']??''),'','SUMMARY','Eligible: '.($r['summary']['eligible']??0),'Needs review: '.($r['summary']['needs_review']??0),'Not eligible: '.($r['summary']['not_eligible']??0),'','TOP RESULTS'];foreach(array_slice($r['results']??[],0,18) as $x)$lines[]=($x['title']??'Scholarship').' — '.strtoupper(str_replace('_',' ',$x['status']??'')).' — '.($x['score']??0).'%';$pdf=$this->pdf($lines);return response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="d2d-eligibility-result.pdf"','Cache-Control'=>'private, no-store']);}
 private function pdf(array $lines):string{$objs=[];$add=function($b)use(&$objs){$objs[]=$b;return count($objs);};$cat=$add('');$pages=$add('');$font=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');$bold=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');$cmd=['0.956 0.686 0 rg 0 805 595 37 re f','0 0 0 rg'];$y=770;foreach($lines as $i=>$line){$sz=$i<2?($i===0?18:15):10;$f=$i<2?'F2':'F1';$t=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);$cmd[]="BT /$f $sz Tf 58 $y Td ($t) Tj ET";$y-=$sz+8;if($y<70)break;}$stream=implode("\n",$cmd);$co=$add('<< /Length '.strlen($stream)." >>\nstream\n$stream\nendstream");$page=$add("<< /Type /Page /Parent $pages 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 $font 0 R /F2 $bold 0 R >> >> /Contents $co 0 R >>");$objs[$cat-1]="<< /Type /Catalog /Pages $pages 0 R >>";$objs[$pages-1]="<< /Type /Pages /Kids [$page 0 R] /Count 1 >>";$pdf="%PDF-1.4\n";$offs=[0];foreach($objs as $i=>$b){$offs[$i+1]=strlen($pdf);$n=$i+1;$pdf.="$n 0 obj\n$b\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objs)+1)."\n0000000000 65535 f \n";for($i=1;$i<=count($objs);$i++)$pdf.=sprintf('%010d 00000 n ', $offs[$i])."\n";$pdf.="trailer\n<< /Size ".(count($objs)+1)." /Root $cat 0 R >>\nstartxref\n$xref\n%%EOF";return $pdf;}}
''')

w(PORTAL,'app/Http/Controllers/D2dPortalSavedResultsV3Controller.php',r'''
<?php
namespace App\Http\Controllers;
use App\Services\D2dPortalProfileSyncV3; use App\Services\D2dResultPdfV3; use Illuminate\Support\Facades\DB; use Illuminate\Support\Str;
class D2dPortalSavedResultsV3Controller extends Controller {
 public function claim(string $token,D2dPortalProfileSyncV3 $sync){$row=DB::table('d2d_student_tool_results')->where('claim_token',$token)->first();abort_unless($row,404);$uid=auth()->id();if($row->user_id!==null&&(string)$row->user_id!==(string)$uid)abort(403);$p=json_decode((string)$row->input_json,true)?:[];$fields=$sync->fillMissing($uid,$p);DB::table('d2d_student_tool_results')->where('id',$row->id)->update(['user_id'=>$uid,'claim_token'=>null,'claimed_at'=>now(),'updated_at'=>now()]);return redirect('/saved-results/'.$row->public_id)->with('status','Result saved to your D2D account.'.($fields?' Profile filled: '.implode(', ',$fields):''));}
 public function index(){$rows=DB::table('d2d_student_tool_results')->where('user_id',auth()->id())->orderByDesc('created_at')->get();return view('d2d-results-v3.index',compact('rows'));}
 public function show(string $id){$row=$this->owned($id);return view('d2d-results-v3.show',['row'=>$row,'profile'=>json_decode((string)$row->input_json,true)?:[],'result'=>json_decode((string)$row->result_json,true)?:[]]);}
 public function share(string $id){$row=$this->owned($id);$t=$row->share_token?:Str::random(48);DB::table('d2d_student_tool_results')->where('id',$row->id)->update(['share_token'=>$t,'is_shared'=>1,'updated_at'=>now()]);return back()->with('share_url','https://dares2dream.com/results/share/'.$t);}
 public function revoke(string $id){$row=$this->owned($id);DB::table('d2d_student_tool_results')->where('id',$row->id)->update(['share_token'=>null,'is_shared'=>0,'updated_at'=>now()]);return back()->with('status','Sharing disabled.');}
 public function pdf(string $id,D2dResultPdfV3 $pdf){return $pdf->download($this->owned($id));}
 public function destroy(string $id){$row=$this->owned($id);DB::table('d2d_student_tool_results')->where('id',$row->id)->delete();return redirect('/saved-results')->with('status','Saved result deleted.');}
 private function owned(string $id):object{$r=DB::table('d2d_student_tool_results')->where('public_id',$id)->where('user_id',auth()->id())->first();abort_unless($r,404);return $r;}
}
''')

w(PORTAL,'resources/views/d2d-results-v3/index.blade.php',r'''
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Saved Results — D2D Portal</title><link rel="stylesheet" href="/d2d-results-v3.css?v=3"></head><body><header class="top"><strong>D2D STUDENT PORTAL · SAVED RESULTS</strong><a href="/student">Back to Portal →</a></header><main>@if(session('status'))<div class="notice">{{session('status')}}</div>@endif<div class="head"><div><span>SAVED RESULTS</span><h1>Your tool history</h1><p>Results saved to this existing student account.</p></div><a class="gold" href="https://dares2dream.com/tools/eligibility-checker">Run Eligibility Checker →</a></div><div class="grid">@forelse($rows as $row)@php($s=json_decode((string)$row->summary_json,true)?:[])<article><span>{{strtoupper(str_replace('-',' ',$row->tool_key))}}</span><h2>{{$row->title}}</h2><p>{{\Illuminate\Support\Carbon::parse($row->created_at)->format('M j, Y · g:i A')}}</p><div class="stats"><b>{{$s['eligible']??0}}<small>Eligible</small></b><b>{{$s['needs_review']??0}}<small>Review</small></b><b>{{$s['not_eligible']??0}}<small>Not eligible</small></b></div><a class="gold" href="/saved-results/{{$row->public_id}}">View result →</a></article>@empty<div class="empty">No saved results yet.</div>@endforelse</div></main></body></html>
''')

w(PORTAL,'resources/views/d2d-results-v3/show.blade.php',r'''
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Eligibility Result — D2D Portal</title><link rel="stylesheet" href="/d2d-results-v3.css?v=3"></head><body><header class="top"><strong>D2D STUDENT PORTAL · SAVED RESULTS</strong><a href="/student">Back to Portal →</a></header><main><a class="back" href="/saved-results">← Saved Results</a>@if(session('status'))<div class="notice">{{session('status')}}</div>@endif @if(session('share_url'))<div class="notice"><strong>Share link:</strong> <input value="{{session('share_url')}}" readonly onclick="this.select()"></div>@endif @php($s=$result['summary']??[])<section class="detail"><div class="head"><div><span>SCHOLARSHIP ELIGIBILITY CHECK</span><h1>{{$row->title}}</h1><p>Saved {{\Illuminate\Support\Carbon::parse($row->created_at)->format('M j, Y')}}</p></div><div class="actions"><a class="gold" href="/saved-results/{{$row->public_id}}/pdf">Download PDF</a>@if(!$row->is_shared)<form method="post" action="/saved-results/{{$row->public_id}}/share">@csrf<button>Share</button></form>@else<form method="post" action="/saved-results/{{$row->public_id}}/share/revoke">@csrf<button>Disable sharing</button></form>@endif</div></div><div class="stats big"><b>{{$s['eligible']??0}}<small>Eligible</small></b><b>{{$s['needs_review']??0}}<small>Needs review</small></b><b>{{$s['not_eligible']??0}}<small>Not eligible</small></b></div><h2>Profile used</h2><div class="profile"><div>Citizenship<strong>{{$profile['citizenship']??'—'}}</strong></div><div>Target degree<strong>{{$profile['target_level']??'—'}}</strong></div><div>Field<strong>{{$profile['field']??'—'}}</strong></div><div>GPA<strong>{{$profile['gpa']??'—'}} {{$profile['gpa_scale']??''}}</strong></div></div><h2>All scholarship results</h2>@foreach($result['results']??[] as $x)<article class="row"><div><strong>{{$x['title']??'Scholarship'}}</strong><small>{{implode(', ',$x['destination_countries']??[])}} · {{$x['score']??0}}%</small>@php($n=array_merge($x['fail_reasons']??[],$x['review_reasons']??[],$x['pass_reasons']??[]))@if($n)<p>{{$n[0]}}</p>@endif</div><span class="pill {{$x['status']??''}}">{{str_replace('_',' ',ucfirst($x['status']??''))}}</span></article>@endforeach<form method="post" action="/saved-results/{{$row->public_id}}" class="delete" onsubmit="return confirm('Delete this saved result?')">@csrf @method('DELETE')<button>Delete saved result</button></form></section></main></body></html>
''')

w(PORTAL,'public/d2d-results-v3.css',r'''
:root{--g:#f4af00;--b:#0d0e10;--line:#ddd;--bg:#f4f4f1}*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Manrope,Inter,Arial,sans-serif;color:#15161a}.top{background:#111;color:#fff;padding:13px max(20px,calc((100vw - 1180px)/2));display:flex;justify-content:space-between;font-size:10px}.top a{color:var(--g)}main{width:min(1180px,calc(100% - 36px));margin:36px auto 70px}.head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;margin-bottom:22px}.head span,article>span{color:#9d6b00;font-size:8px;font-weight:800;letter-spacing:.15em}.head h1{margin:7px 0;font-size:32px}.head p,article>p{color:#777;font-size:10px}.gold{display:inline-flex;align-items:center;min-height:42px;padding:0 15px;border-radius:8px;background:var(--g);color:#111;text-decoration:none;font-size:10px;font-weight:800}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.grid article,.detail{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px}.grid h2{font-size:17px}.stats{display:grid;grid-template-columns:repeat(3,1fr);border:1px solid #e1e1e1;border-radius:9px;overflow:hidden;margin:16px 0}.stats b{padding:12px;text-align:center}.stats b+b{border-left:1px solid #e1e1e1}.stats small{display:block;margin-top:4px;color:#999;font-size:8px}.big{max-width:620px}.actions{display:flex;gap:8px}.actions form{margin:0}.actions button{min-height:42px;border:1px solid #ccc;border-radius:8px;background:#fff;padding:0 13px;font-weight:700}.profile{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}.profile div{border:1px solid #ddd;border-radius:9px;padding:13px;color:#999;font-size:8px}.profile strong{display:block;margin-top:5px;color:#111;font-size:11px}.row{display:flex;justify-content:space-between;gap:16px;padding:14px 0;border-bottom:1px solid #eee}.row small{display:block;margin-top:4px;color:#888}.row p{font-size:9px;color:#666}.pill{align-self:flex-start;border-radius:999px;padding:6px 9px;font-size:8px;font-weight:800}.pill.eligible{background:#111;color:#fff}.pill.needs_review{background:#fff0bd}.pill.not_eligible{background:#eee}.notice{padding:13px;border:1px solid #eed06d;background:#fff8d9;border-radius:9px;margin-bottom:15px}.notice input{width:70%}.back{font-size:10px;color:#111}.delete{margin-top:25px}.delete button{border:0;background:none;color:#9b2b2b;text-decoration:underline}.empty{grid-column:1/-1;background:#fff;padding:40px;border:1px dashed #ccc;border-radius:14px;text-align:center}
@media(max-width:900px){.grid{grid-template-columns:1fr}.profile{grid-template-columns:1fr 1fr}.head{align-items:flex-start;flex-direction:column}}
''')

w(PORTAL,'routes/d2d-portal-results-v3-console.php',r'''
<?php
use Illuminate\Support\Facades\Artisan;use Illuminate\Support\Facades\DB;
Artisan::command('d2d:portal-results-v3-doctor',function(){$x=DB::selectOne('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['d2d_student_tool_results']);$this->info('D2D Portal Saved Results V3');$this->line('Table: '.((int)($x->c??0)>0?'OK':'MISSING'));$this->line('Portal navigation/layout is NOT modified by this package.');});
''')

# ---------------- INSTALLERS ----------------
provider_patch = r'''
def register_provider(path, provider):
    txt=path.read_text()
    if provider in txt:return
    pos=txt.rfind('];')
    if pos<0:raise RuntimeError('Cannot safely edit bootstrap/providers.php')
    before=txt[:pos].rstrip()
    comma='' if before.endswith(',') else ','
    path.write_text(before+comma+'\n    '+provider+',\n'+txt[pos:])
'''

# Public installer
(PUB/'install-d2d-eligibility-v3.php').write_text(r'''<?php
declare(strict_types=1);$base=__DIR__;$payload=$base.'/payload';echo "=== D2D ELIGIBILITY V3 PUBLIC ===\n";if(!is_file($base.'/artisan')||str_contains($base,'portal.dares2dream.com')||str_contains($base,'crm.dares2dream.com')){fwrite(STDERR,"Wrong application. Install in /home/daresdre/d2d-laravel\n");exit(1);} $stamp=date('Ymd-His');$backup=$base.'/storage/app/d2d-backups/elig-v3-'.$stamp;@mkdir($backup,0775,true);$targets=['app/Providers/D2dEligibilityV3ServiceProvider.php','app/Services/Tools/D2dScholarshipCatalog.php','app/Services/Tools/D2dEligibilityEngine.php','app/Http/Controllers/Tools/D2dEligibilityV3Controller.php','resources/views/tools/eligibility-v3.blade.php','resources/views/tools/eligibility-shared-v3.blade.php','public/d2d-tools/eligibility-v3.css','public/d2d-tools/eligibility-v3.js','routes/d2d-eligibility-v3-console.php'];foreach($targets as $r){$s=$payload.'/'.$r;$d=$base.'/'.$r;if(!is_file($s)){fwrite(STDERR,"Missing $r\n");exit(1);}if(is_file($d)){@mkdir(dirname($backup.'/'.$r),0775,true);copy($d,$backup.'/'.$r);}@mkdir(dirname($d),0775,true);copy($s,$d);echo "Installed $r\n";} $p=$base.'/bootstrap/providers.php';copy($p,$backup.'/providers.php');$txt=file_get_contents($p);$prov='App\\Providers\\D2dEligibilityV3ServiceProvider::class';if(!str_contains($txt,$prov)){$pos=strrpos($txt,'];');if($pos===false){fwrite(STDERR,"Cannot register provider\n");exit(1);}$before=rtrim(substr($txt,0,$pos));$comma=str_ends_with($before,',')?'':',';file_put_contents($p,$before.$comma."\n    $prov,\n".substr($txt,$pos));}file_put_contents($base.'/storage/app/d2d-elig-v3-backup.txt',$backup);echo "\nNo migrations. No Portal/CRM files touched.\nRun: php artisan optimize:clear && php artisan d2d:eligibility-v3-doctor\n";
''',encoding='utf-8')

# Portal installer with additive table
(PORTAL/'install-d2d-portal-results-v3.php').write_text(r'''<?php
declare(strict_types=1);$base=__DIR__;$payload=$base.'/payload';echo "=== D2D PORTAL SAVED RESULTS V3 ===\n";if(!is_file($base.'/artisan')||str_contains($base,'d2d-laravel')||str_contains($base,'crm.dares2dream.com')){fwrite(STDERR,"Wrong application. Install in /home/daresdre/portal.dares2dream.com\n");exit(1);} $stamp=date('Ymd-His');$backup=$base.'/storage/app/d2d-backups/results-v3-'.$stamp;@mkdir($backup,0775,true);$targets=['app/Providers/D2dPortalSavedResultsV3ServiceProvider.php','app/Services/D2dPortalProfileSyncV3.php','app/Services/D2dResultPdfV3.php','app/Http/Controllers/D2dPortalSavedResultsV3Controller.php','resources/views/d2d-results-v3/index.blade.php','resources/views/d2d-results-v3/show.blade.php','public/d2d-results-v3.css','routes/d2d-portal-results-v3-console.php'];foreach($targets as $r){$s=$payload.'/'.$r;$d=$base.'/'.$r;if(!is_file($s)){fwrite(STDERR,"Missing $r\n");exit(1);}if(is_file($d)){@mkdir(dirname($backup.'/'.$r),0775,true);copy($d,$backup.'/'.$r);}@mkdir(dirname($d),0775,true);copy($s,$d);echo "Installed $r\n";} $p=$base.'/bootstrap/providers.php';copy($p,$backup.'/providers.php');$txt=file_get_contents($p);$prov='App\\Providers\\D2dPortalSavedResultsV3ServiceProvider::class';if(!str_contains($txt,$prov)){$pos=strrpos($txt,'];');if($pos===false){fwrite(STDERR,"Cannot register provider\n");exit(1);}$before=rtrim(substr($txt,0,$pos));$comma=str_ends_with($before,',')?'':',';file_put_contents($p,$before.$comma."\n    $prov,\n".substr($txt,$pos));} require $base.'/vendor/autoload.php';$app=require $base.'/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();\Illuminate\Support\Facades\DB::statement("CREATE TABLE IF NOT EXISTS d2d_student_tool_results (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) NOT NULL UNIQUE, user_id BIGINT UNSIGNED NULL, tool_key VARCHAR(80) NOT NULL, tool_version VARCHAR(24) NOT NULL DEFAULT '3.0', title VARCHAR(255) NOT NULL, input_json LONGTEXT NOT NULL, result_json LONGTEXT NOT NULL, summary_json LONGTEXT NULL, claim_token VARCHAR(80) NULL UNIQUE, share_token VARCHAR(80) NULL UNIQUE, is_shared TINYINT(1) NOT NULL DEFAULT 0, claimed_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, INDEX(user_id), INDEX(tool_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");file_put_contents($base.'/storage/app/d2d-results-v3-backup.txt',$backup);echo "\nOne additive table is ready. No existing table altered. Portal nav/layout untouched.\nRun: php artisan optimize:clear && php artisan d2d:portal-results-v3-doctor\n";
''',encoding='utf-8')

# Rollbacks
for base,name,pointer in [(PUB,'rollback-d2d-eligibility-v3.php','d2d-elig-v3-backup.txt'),(PORTAL,'rollback-d2d-portal-results-v3.php','d2d-results-v3-backup.txt')]:
    (base/name).write_text(f'''<?php\ndeclare(strict_types=1);$base=__DIR__;$p=$base.'/storage/app/{pointer}';if(!is_file($p)){{fwrite(STDERR,"Backup pointer missing\\n");exit(1);}}$b=trim(file_get_contents($p));if(!is_dir($b)){{fwrite(STDERR,"Backup missing\\n");exit(1);}}$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($b,FilesystemIterator::SKIP_DOTS));foreach($it as $f){{if(!$f->isFile())continue;$r=substr($f->getPathname(),strlen($b)+1);if($r==='providers.php')$d=$base.'/bootstrap/providers.php';else $d=$base.'/'.$r;@mkdir(dirname($d),0775,true);copy($f->getPathname(),$d);echo "Restored $r\\n";}}echo "Saved-results table is intentionally preserved. Run php artisan optimize:clear\\n";\n''',encoding='utf-8')

(ROOT/'README.md').write_text(r'''# D2D Tool #1 — Eligibility Checker V3 + Portal Saved Results

Install the **Portal part first**, then the public part.

## 1) Portal
Extract `portal-app/` into `/home/daresdre/portal.dares2dream.com/`, then:

```bash
cd ~/portal.dares2dream.com
php install-d2d-portal-results-v3.php
php artisan optimize:clear
php artisan d2d:portal-results-v3-doctor
```

This creates one additive table: `d2d_student_tool_results`. It does not alter users, profiles, applications, messages, documents, payments, or Portal navigation/layout.

## 2) Public Laravel
Extract `public-app/` into `/home/daresdre/d2d-laravel/`, then:

```bash
cd ~/d2d-laravel
php install-d2d-eligibility-v3.php
php artisan optimize:clear
php artisan d2d:eligibility-v3-doctor
```

Open: `https://dares2dream.com/tools/eligibility-checker`

## Logic
The checker merges a predefined D2D master catalogue with published scholarship records from the shared `opportunities` table. CRM values override matching predefined fields when available. It evaluates every scholarship in one run and returns `Eligible`, `Needs review`, or `Not eligible` with a match score and reasons. Old stored-cycle deadlines are labelled as past and must be verified on the official site.

## Save architecture
There is no public-site student account. Save creates a pending result in the shared Portal-owned table and redirects to `portal.dares2dream.com/tool-results/claim/{token}`. Portal's existing `auth` middleware owns login. After login, the result is attached to the existing Portal `auth()->id()`.

When claiming a result, the Portal profile sync safely fills only missing values in an existing `student_profiles`, `student_profile`, or `profiles` row when matching columns exist. It does not overwrite non-empty profile data.

Saved Results live at `https://portal.dares2dream.com/saved-results`. Share and PDF actions are available there. Portal navigation is deliberately not modified by this package.

## Rollback
Public: `php rollback-d2d-eligibility-v3.php && php artisan optimize:clear`
Portal: `php rollback-d2d-portal-results-v3.php && php artisan optimize:clear`

The additive saved-results table is preserved on rollback so student data is not lost.
''',encoding='utf-8')

# PHP syntax validation
bad=[]
for p in ROOT.rglob('*.php'):
    r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
    if r.returncode: bad.append((str(p),r.stdout,r.stderr))
if bad:
    for x in bad: print(x)
    raise SystemExit('PHP syntax validation failed')

OUT.parent.mkdir(parents=True,exist_ok=True)
with zipfile.ZipFile(OUT,'w',zipfile.ZIP_DEFLATED) as z:
    for p in ROOT.rglob('*'):
        if p.is_file(): z.write(p,p.relative_to(ROOT))
with zipfile.ZipFile(OUT) as z:
    assert z.testzip() is None
print('built',OUT,OUT.stat().st_size)
