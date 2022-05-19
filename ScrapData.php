<?php


namespace LeilaoMax\Classes\Scrap\Base;


use GuzzleHttp\Client;
use RecordResult;
use Save;
use SaveImage;
use StartLogResult;
use Auction;
use Symfony\Component\DomCrawler\Crawler;

abstract class ScrapData
{
    protected $result;
    protected $pagination = [];
    protected $info;
    protected $link;
    protected $index;
    protected $node;
    protected $nodepgContent;
    protected $innerNode;
    protected $auction;
    protected $category;
    protected $sub_category;
    protected $title;
    protected $featured_image;
    protected $code;
    protected $type;
    protected $minimum_value;
    protected $price_valuation;
    protected $discount;
    protected $dates;
    protected $location;
    protected $status_auction;
    protected $locations;
    protected $sale_type;
    protected $recorded_ids = [];
    protected $counter = 0;
    protected $isSetted = [ 'category'     => false,
                            'sub_category' => false,
                            'location'     => false,
                            'date'         => false,
                            'status'       => false];


    public static function process($refer , $cat, $categoryToSeach){
        return  (new static)->handle($refer, $cat, $categoryToSeach);
    }

    private function handle($refer,$cat, $categoryToSeach){
        return   $this->setAuctionerInfo($cat, $categoryToSeach, $refer)
                      ->setPagination()
                      ->setLogResult()
                      ->start()
                      ->returnRecordedIds();
    }
    // CONFIG
    public abstract function setInfo($cat, $categoryToSeach, $refer);
    // SET PAGINATION
    public abstract function setPaginationData();
    // GET NODE LIST
    public abstract function getNodeList($page, $index);
    // SET LINK
    public abstract function link();
    // GET INNER NODE
    public abstract function getInnerNode();
    // SET IS VALID AUCTION
    public abstract function isValidAuction();
    // SET IS VALID AUCTION
    public abstract function dates();
    // SET IS VALID AUCTION
    public abstract function locations();
    // SET CARTEGOY
    public abstract function category();
    // SET SUB CARTEGOY
    public abstract function sub_category();
    // SET TITLE
    public abstract function title();
    // SET FEATURED IMAGE
    public abstract function featured_image();
    // TYPE
    public abstract function type();
    // CODE
    public abstract function code();
    // MINIMUM VALUE
    public abstract function minimum_value();
    // PRICE VALUATION
    public abstract function price_valuation();
    // STATUS AUCTION
    public abstract function status_auction();
    // DISCOUNT
    public abstract function discount();
    // SALE TYPE
    public abstract function sale_type();

    // SET AUCTIONNER INFO
    public function setAuctionerInfo($cat, $categoryToSeach, $refer){
        $this->info = $this->setInfo($cat, $categoryToSeach, $refer);
        return $this;
    }
    // SET PAGINATION
    public function setPagination(){
        // SET NO PAGINATION
        if(array_key_exists('hasPagination', $this->info)){
            if($this->info['hasPagination'] === false){
                $this->pagination[0] = $this->info['searchUrlTemp'];
                return $this;
            }
        }
        // SET PAGINATION
        $this->pagination = $this->setPaginationData();
        return $this;
    }

    // SET PAGINATION
    private function setLogResult(){
        StartLogResult::process($this->info['auctioneer_id']);
        return $this;
    }

    // SAVE BOOK
    private function start()
    {
        foreach ($this->pagination as $index => $page) {
            // GET NODE CONTENT
            if(array_key_exists('hasNodeContent', $this->info)){
                if($this->info['hasNodeContent'] === true){
                    $this->nodepgContent = $this->getNodePgContent($page, $index);
                }
            }
            // GET NODE CONTENT
            if(array_key_exists('getFromContent', $this->info)){
                if(in_array('category', $this->info['getFromContent'])) {
                    $this->category             = $this->category();
                    $this->isSetted['category'] = true;
                }

                if(in_array('sub_category', $this->info['getFromContent'])) {
                    $this->sub_category             = $this->sub_category();
                    $this->isSetted['sub_category'] = true;
                }

                if(in_array('dates', $this->info['getFromContent'])) {
                        $this->dates            = $this->dates();
                        $this->isSetted['date'] = true;
                }

                if(in_array('location', $this->info['getFromContent'])) {
                    $this->location                = $this->locations();
                    $this->isSetted['location'] = true;
                }

                if(in_array('status', $this->info['getFromContent'])) {
                    $this->status_auction     = $this->status_auction();
                    $this->isSetted['status'] = true;
                }
            }


            $crawler = $this->getNodeList($page, $index);
            try {
                $crawler->each(
                    function (Crawler $node, $index)  {
                            if(!$node ){
                                return;
                            }
                            // SET INDEX
                            $this->index = $index;
                            // SET NODE
                            $this->node = $node;
                            // SET LINK
                            $this->link = $this->link();
                            // VERIFY IF THE LINK EXISTS
                            $this->innerNode = $this->getInnerNode();
                            // EXTRA VALIDATION
                            $isValidAuction =   $this->isValidAuction();
                            if(!$isValidAuction){
                                echo "removeu ->" . $this->link . "\n";
                                return;
                            }
                            // SET TITLE
                            $this->title = $this->title();
                            // SET CATEGORY
                            if($this->isSetted['category'] !== true){
                                 $this->category = $this->category();
                            }
                            // SET SUB CATEGORY
                            if($this->isSetted['sub_category'] !== true) {
                                $this->sub_category = $this->sub_category();
                            }
                            // SET AUCTION
                            $this->auction = self::getAuction($this->link);
                            // SET DATES
                            if($this->isSetted['date'] !== true) {
                                 $this->dates = $this->dates();
                            }
                            // SET LOCATIONS
                            if($this->isSetted['location'] !== true){
                                $this->location = $this->locations();
                            }
                            // SET FEATURED IMAGE
                            $this->featured_image = $this->featured_image();
                            // SET TYPE
                            $this->type = $this->type();
                            // SET CODE
                            $this->code = $this->code();
                            // SET MINIMUM VALUE
                            $this->minimum_value = $this->minimum_value();
                            // SET PRICE EVALUATION
                            $this->price_valuation = $this->price_valuation();
                            // SET DISCOUNT
                            $this->discount       = $this->discount();
                            // SET STATUS AUCTION
                           if($this->isSetted['status'] !== true) {
                               $this->status_auction = $this->status_auction();
                           }
                            // SET INFO WITH INDEX
                            $info_with_index = array_merge([ 'index' => $index], $this->info );
                            // SET SALE TYPE
                            $this->sale_type = $this->sale_type();
                            // VERIFY HAS MAIN VALUES
                            if(!$this->verifyMainValues()){
                                return;
                            }
                            $node = [
                                'title'                     => $this->title,
                                'link'                      => $this->link,
                                'featured_image'            => $this->featured_image ,
                                'auctioneer_id'             => $this->info['auctioneer_id'],
                                'type'                      => $this->type,
                                'code'                      => $this->code,
                                'date_first'                => $this->dates['date_first'],
                                'date_second'               => $this->dates['date_second'],
                                'minimum_value_first'       => $this->minimum_value['minimum_value_first'],
                                'minimum_value_second'      => $this->minimum_value['minimum_value_second'],
                                'category_id'               => $this->category ,
                                'subcategory_id'            => $this->sub_category ,
                                'status_auction'            => $this->status_auction,
                                'address'                   => $this->location['address'],
                                'neighborhood'              => $this->location['neighborhood'],
                                'city_id'                   => $this->location['city_id'],
                                'state_id'                  => $this->location['state_id'],
                                'discount'                  => $this->discount,
                                'refer'                     => $this->info['refer'],
                                'neighborhood_id'           => null,
                                'latitude'                  => $this->location['latitude'],
                                'longitude'                 => $this->location['longitude'],
                                'cep'                       => null,
                                'price_valuation'           => $this->price_valuation,
                                'seller_id'                 => null,
                                'method_auction_presential' => null,
                                'method_auction_electronic' => null,
                                'sale_type'                 => $this->sale_type,
                                'total_visits'              => 0,
                                'brand_id'                  => null,
                                'model'                     => null,
                                'extra'                     => null,
                                'classification'            => null
                            ];
                            echo"\n" . $this->link  ."\n";
                            // SAVE THE RESULTS
                            $save
                                = // SAVE RESULTS
                                new RecordResult(
                                // SAVE
                                    new Save(
                                    // SAVE IMAGE
                                        new SaveImage(
                                            $node,
                                            $info_with_index,
                                            $this->auction
                                        )
                                    )
                                );
                            // SLEEP EACH 10
                            $this->counter ++;
                            if($this->counter === 20){
                                $this->counter = 0;
                                sleep(10);
                            }

                            $id_recorded = $save->process();
                            if ($id_recorded) {
                                echo "\n" . $id_recorded . "\n";
                                // PROCESS
                                array_push($this->recorded_ids, $id_recorded);
                            }
                        }
                    );
            }
            catch (GuzzleHttp\Exception\ClientException $e) {
                echo "erro";
            }
        }
        return $this;
    }

    private function verifyMainValues(){
        // HAS LOCATION
        if(!$this->location['city_id']){
            return false;
        }

        // HAS CATEGOY
        if(! $this->category){
            return false;
        }

        if(array_key_exists('hasDateFirst', $this->info)){
            if($this->info['hasDateFirst'] === false){
                return true;
            }
        }
        // HAS MIN VALUE
        if(! $this->minimum_value['minimum_value_first']){
            return false;
        }
        return true;
    }

    // RETURN IDS
    private function returnRecordedIds(){
        return $this->recorded_ids;
    }

    private static function getAuction($link){
        return Auction::where('link', $link)->first();
    }


}
