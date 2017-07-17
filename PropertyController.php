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
use Auth;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Request;

class PropertyController extends Controller
{
    protected $propRepo;
    protected $distRepo;
    protected $wardRepo;
    protected $streetRepo;
    protected $search_engine;

    public function __construct(PropertyRepository $propertyRepository
        ,DistrictRepository $districtRepository
        ,WardRepository $wardRepository
        ,StreetRepository $streetRepository
        ,ISphinxSearchHelper $searchHelper
    )
    {
        $this->propRepo = $propertyRepository;
        $this->distRepo = $districtRepository;
        $this->wardRepo = $wardRepository;
        $this->streetRepo = $streetRepository;
        $this->search_engine = $searchHelper;
    }

    public function info($id){
        $prop = $this->propRepo->findByField('id',$id)->first();

        if(is_null($prop)) return redirect()->route('Batdongsansg24hHome.index');

        $user = Auth::guard('bds_sg24h.users')->user();
        $allowEdit = (isset($user) && ($user->id == $prop->owner_user_id || $user->phone == $prop->owner_user_phone));
//        $allowEdit = true;

//        $relate_props = null;
        $area_info = [];

        $kw = '';

//        dd($prop);

        empty($prop->street_id)?:$kw .= $this->streetRepo->getAllStreetsCache()->where('id', intval($prop->street_id))->first()->street_name;
        empty($prop->ward_id)?:$kw .= ' '.$this->wardRepo->getAllWardsCache()->where('id', intval($prop->ward_id))->first()->ward_name;
        empty($prop->district_id)?:$kw .= ' '.$this->distRepo->getAllDistrictsCache()->where('id', intval($prop->district_id))->first()->district_name;

        $relate_props = $this->search_engine->search($kw, 1, 10, [['msg_sku', $prop->msg_sku, true]], 'timestamp_on_market DESC')['matches'];

//        if($prop->street_id > 0){
//            $relate_props = $this->propRepo->findWhereLimit(['street_id' => $prop->street_id, ['id','!=',$prop->id]],10);
//        } else if(count($relate_props) < 3 && $prop->ward_id > 0) {
//            $relate_props = $this->propRepo->findWhereLimit(['ward_id' => $prop->ward_id, ['id','!=',$prop->id]],10);
//        } else {
//            $relate_props = $this->propRepo->findWhereLimit(['district_id' => $prop->district_id, ['id','!=',$prop->id]],10);
//        }

        if($prop->district_id > 0){
            $district = $this->distRepo->getAllDistrictsCache()->where('id', intval($prop->district_id))->first();
            $area_info['name'] = Enum::$PROPERTY_TYPES[$prop->property_type_code].' '.$district->district_name;
            $area_info['url'] = route('Batdongsansg24hFilter.filter_proptype_district',[UtilHelper::slug( Enum::$PROPERTY_TYPES[$prop->property_type_code]), $district->district_alias, $prop->property_type_code, $prop->district_id]);

        }


        return view("Batdongsansg24h::property.info")->with([
            'prop' => $prop,
            'relate_props'=> $relate_props,
            'area_info' => $area_info,
            'allowEdit' => $allowEdit
        ]);
    }
}