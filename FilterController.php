<?php
/**
 * Created by PhpStorm.
 * User: huytt
 * Date: 5/3/2017
 * Time: 9:17 AM
 */

namespace App\Modules\Batdongsansg24h\Controllers;


use App\Modules\Batdongsansg24h\Enum;
use App\Modules\Core\BDS_SG24h\Repositories\DistrictRepository;
use App\Modules\Core\BDS_SG24h\Repositories\PropertyRepository;
use App\Modules\Core\BDS_SG24h\Repositories\StreetRepository;
use App\Modules\Core\BDS_SG24h\Repositories\WardRepository;
use App\Modules\Core\Constant;
use App\Modules\Core\Inf\ISphinxSearchHelper;
use App\Modules\Core\Utils\UtilHelper;
use Illuminate\Routing\Controller;
use Redirect;
use Session;
use sngrl\SphinxSearch\SphinxSearch;
use Sphinx\SphinxClient;
use Symfony\Component\HttpFoundation\Request;

class FilterController extends Controller
{
    protected $propRepo;
    protected $disRepo;
    protected $wardRepo;
    protected $streetRepo;
    protected $search_engine;

    public function __construct(
        PropertyRepository $propertyRepository
        , DistrictRepository $districtRepository
        , WardRepository $wardRepository
        , StreetRepository $streetRepository
        , ISphinxSearchHelper $searchHelper
    )
    {
        $this->propRepo = $propertyRepository;
        $this->disRepo = $districtRepository;
        $this->wardRepo = $wardRepository;
        $this->streetRepo = $streetRepository;
        $this->search_engine = $searchHelper;
    }
    
    public function filter_district($dis_alias, $distId, Request $request){
        return $this->generate_search_filter_view($request, null, $distId);
    }

    public function filter_district_ward($dis_alias, $ward_alias, $distId, $wardId, Request $request){
        return $this->generate_search_filter_view($request, null, $distId, $wardId);
    }

    public function filter_proptype($pt_alias, $pt_code, Request $request){
        return $this->generate_search_filter_view($request, $pt_code);
    }

    public function filter_proptype_district($pt_alias, $dis_alias, $pt_code, $distId, Request $request){
        return $this->generate_search_filter_view($request, $pt_code, $distId);
    }

    public function filter_proptype_district_ward($pt_alias, $dis_alias, $ward_alias, $pt_code, $distId, $wardId, Request $request){
        return $this->generate_search_filter_view($request, $pt_code, $distId, $wardId);
    }

    public function filter_proptype_street($pt_alias, $street_alias, $dis_alias, $pt_code, $streetId, $dis_id, Request $request){
        return $this->generate_search_filter_view($request, $pt_code, $dis_id, null, $streetId);
    }

    public function generate_search_filter_view(Request $request, $pt_code, $distId = null, $wardId = null, $streetId = null, $kw = null){
//                dd($pt_alias, $dis_alias, $distId, $ward_alias, $pt_code, $distId, $wardId);
        $condition = [];
        $paginator = null;
        $from_price = $request->input('from_price');
        $to_price = $request->input('to_price');
        $title = $request->input('t');

        empty($pt_code)?:$curPt = $condition['property_type_code'] = $pt_code;
        empty($distId)?:$curDist = $condition['district_id'] = $distId;
        empty($wardId)?:$curWard = $condition['ward_id'] = $wardId;
        empty($streetId)?:$curStreet = $condition['street_id'] = $streetId;

        $curFromPrice = $from_price;
        $curToPrice = $to_price;
//        dd($condition);

        if(!is_null($kw)) {
            $range = [0, 99999999999];
            empty($from_price)?:($range[0]= $from_price);
            empty($to_price)?:($range[1] = $to_price);
            $condition['requested_price_value'] = $range;

            $page = $request->input('page', 1);
//            $sphinx = new SphinxSearch();
//        $results = $sphinx->search('pham huu lau','bds_sg24h')->setMatchMode(SphinxClient::SPH_MATCH_EXTENDED)->limit(20)->filter('district_id',7)->get();
//            $results = $sphinx->search($kw, 'bds_sg24h')->setMatchMode(SphinxClient::SPH_MATCH_ANY)->limit(Constant::BDS_PER_PAGE_DEFAULT, $page - 1)->get();
//            $sphinx = $sphinx->search($kw, 'bds_sg24h')->setMatchMode(SphinxClient::SPH_MATCH_ALL)->setSortMode(SphinxClient::SPH_SORT_EXTENDED,'timestamp_on_market DESC');
//            foreach ($condition as $field => $value) {
//                if (is_array($value)) {
//                    list($min, $max) = $value;
//                    $sphinx = $sphinx->range($field,$min,$max);
//                } else {
//                    $sphinx = $sphinx->filter($field,$value);
//                }
//            }
//            $results = $sphinx->limit(Constant::BDS_PER_PAGE_DEFAULT, $page - 1)->get();
            $results = $this->search_engine->search($kw,$page,Constant::BDS_PER_PAGE_DEFAULT,$condition,'timestamp_on_market DESC');
            if($results['total_found'] == 0) return view('Batdongsansg24h::error.search-not-found', compact('kw','curDist', 'curWard', 'curPt', 'curStreet', 'curFromPrice', 'curToPrice'));
//            dd($results, $results['total_found']);
//            $props = $this->propRepo->findWhereInOrder('id', array_keys($results['matches']));
            $paginator = $this->propRepo->makePaginateByCollection($results['matches'], $results['total_found'],Constant::BDS_PER_PAGE_DEFAULT);
        }

        empty($from_price)?:array_push($condition,['requested_price_value','>=',$from_price]);
        empty($to_price)?:array_push($condition,['requested_price_value','<=',$to_price]);

        return view('Batdongsansg24h::home.search-filter', compact('condition', 'kw','curDist', 'curWard', 'curPt', 'curStreet', 'curFromPrice', 'curToPrice','title', 'paginator'));
    }

    public function search(Request $request){
        $kw = $request->input('keyword');
        $district_id = $request->input('district_id');
        $ward_id = $request->input('ward_id');
        $street_id = $request->input('ward_id');
        $property_type_code = $request->input('property_type_code');
        $property_type_alias = empty($property_type_code)?null:UtilHelper::slug(Enum::$PROPERTY_TYPES[$property_type_code]);

        $from_price = $request->input('from_price');
        $to_price = $request->input('to_price');

        if(empty($kw)){
            $dis = $this->disRepo->findByField('id', $district_id)->first();
            $ward = $this->wardRepo->findByField('id', $ward_id)->first();
            $street = $this->streetRepo->findByField('id', $street_id)->first();

            $dname = is_null($dis)?null:$dis->district_alias;
            $wname = is_null($ward)?null:$ward->ward_alias;
            $stname = is_null($street)?null:$street->street_alias;

            if(!empty($property_type_alias) && !is_null($wname) && !is_null($dname)) {
                return Redirect::route('Batdongsansg24hFilter.filter_proptype_district_ward',[
                    $property_type_alias,$dname, $wname, $property_type_code, $district_id, $ward_id,
                    'from_price' => $from_price, 'to_price' => $to_price,
                    't' => sprintf('%1$s | %2$s | %3$s', Enum::$PROPERTY_TYPES[$property_type_code], $ward->ward_name, $dis->district_name)
                ]);
            }

            if(!empty($property_type_alias) && !is_null($stname) && !is_null($dname)) {
                return Redirect::route('Batdongsansg24hFilter.filter_proptype_street',[
                    $property_type_alias,$stname, $dname, $property_type_code, $street_id, $district_id,
                    'from_price' => $from_price, 'to_price' => $to_price,
                    't' => sprintf('%1$s | %2$s | %3$s', Enum::$PROPERTY_TYPES[$property_type_code], $street->street_name, $dis->district_name)
                ]);
            }

            if(!empty($property_type_alias) && !is_null($dname)){
                return Redirect::route('Batdongsansg24hFilter.filter_proptype_district',[
                    $property_type_alias, $dname, $property_type_code, $district_id,
                    'from_price' => $from_price, 'to_price' => $to_price,
                    't' => sprintf('%1$s | %2$s', Enum::$PROPERTY_TYPES[$property_type_code], $dis->district_name)
                ]);
            }

            if(!is_null($wname) && !is_null($dname)) {
                return Redirect::route('Batdongsansg24hFilter.filter_district_ward',[
                    $dname, $wname, $district_id, $ward_id, 'from_price' => $from_price, 'to_price' => $to_price,
                    't' => sprintf('%1$s | %2$s', $ward->ward_name, $dis->district_name)
                ]);
            }

            if(!is_null($dname)){
                return Redirect::route('Batdongsansg24hFilter.filter_district',[$dname, $district_id,
                    'from_price' => $from_price, 'to_price' => $to_price,
                    't' => sprintf($dis->district_name)
                ]);
            }

            if(!is_null($property_type_alias)){
                return Redirect::route('Batdongsansg24hFilter.filter_proptype',[$property_type_alias, $property_type_code,
                    'from_price' => $from_price, 'to_price' => $to_price,
                    't' => sprintf(Enum::$PROPERTY_TYPES[$property_type_code])
                ]);
            }
        } else {
            return $this->generate_search_filter_view($request, $property_type_code, $district_id, $ward_id, $street_id, $kw);
        }

        $title = '';
        return view('Batdongsansg24h::home.search-filter', compact('title'));
    }
}