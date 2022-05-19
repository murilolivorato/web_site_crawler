<?php


namespace LeilaoMax\Classes\Scrap\ParquedosLeiloeiros;


use Carbon\Carbon;
use GuzzleHttp\Client;
use SetCharacter;
use ScrapData;
use CreateCitySolicitation;
use Format;
use VerifyAndSavedAddress;
use Auction;
use Symfony\Component\DomCrawler\Crawler;

class ScrapParquedosLeiloeiros extends ScrapData
{
    // CONFIG
    public function setInfo($cat, $categoryToSeach, $refer){
        return [
            'url'                  => 'https://www.parquedosleiloes.com.br',
            'searchUrlTemp'        => $categoryToSeach,
            'searchInnerTemp'      => 'https://www.parquedosleiloes.com.br/lote/',
            'hasPaginationSegmentdegr' => true,
            'auctioneer_id'        => 54,
            'category_id'          => $cat[0],
            'subcategory_id'       => $cat[1],
            'categoryToSearch'     => $categoryToSeach,
            'hasNodeContent'       => true,
            'refer'                => $refer,
            'header'               => ['Accept' => '*/*', 'User-Agent' => "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.101 Safari/537.36"]
        ];
    }

    // SET THE PAGINATION
    public function setPaginationData(){
        $client        = new Client(['headers' => $this->info['header'], 'base_uri' => 'http://www.parquedosleiloes.com.br/', 'verify' => false]);
        try {
            $html          = $client->request('GET', $this->info['searchUrlTemp']);
            $crawler = new Crawler();
            $crawler->addHtmlContent($html->getBody()->getContents(), 'UTF-8');
            $allLink =  $crawler->filter('ul.pagination > li.page-item')->each(function (Crawler $node, $i) {
               if($node->filter('a')->count()){
                    return $node->filter('a')->attr('href');
               }
            });

            if(!count($allLink)){
                return [$this->info['searchUrlTemp']];
            }

            $allLink = array_merge([$this->info['searchUrlTemp'] . "?page=1"], $allLink);
            return array_unique(array_filter($allLink), SORT_REGULAR);


        } catch (GuzzleHttp\Exception\ClientException $e) {
            return null;
        }
    }

    // SET THE GET NODE LIST
    public function getNodePgContent($page,$index){
        $client        = new Client(['headers' => $this->info['header'], 'base_uri' => 'http://www.parquedosleiloes.com.br/', 'verify' => false]);
        try {
            $html    = $client->request('GET', $page);
            $crawler = new Crawler();
            $crawler->addHtmlContent($html->getBody()->getContents(), 'UTF-8');
            return $crawler->filter('div.auction-details-list');
        } catch (GuzzleHttp\Exception\ClientException $e) {;
            return null;
        }
    }

    // SET THE GET NODE LIST
    public function getNodeList($page,$index){
        $client        = new Client(['headers' => $this->info['header'], 'base_uri' => 'http://www.parquedosleiloes.com.br/', 'verify' => false]);
        try {
            $html    = $client->request('GET', $page);
            $crawler = new Crawler();
            $crawler->addHtmlContent($html->getBody()->getContents(), 'UTF-8');
            return $crawler->filter('div.card.auction-lot-card');
        } catch (GuzzleHttp\Exception\ClientException $e) {
            return null;
        }
    }

    // SET INNER NODE
    public function getInnerNode(){
        $client        = new Client(['headers' => $this->info['header'], 'base_uri' => 'http://www.parquedosleiloes.com.br/', 'verify' => false]);
        try {
            $html          = $client->request('GET', $this->link);
            $crawler = new Crawler();
            $crawler->addHtmlContent($html->getBody()->getContents(), 'UTF-8');
            return $crawler;

        } catch (GuzzleHttp\Exception\ClientException $e) {
            return "error";
        }
    }

    // SET LINK
    public function link() {
        return $this->node->filter('a')->attr('href');
    }

    // SET AUCTION
    public function auction() {
        if(!$this->node){
            return;
        }
        return Auction::where('link', $this->link)->first();
    }

    // SET TITLE
    public function title() {
        if($this->info['category_id'] == "bens diversos") {
            $title = Format::getExText(Format::getFilterText($this->innerNode, "div#info-colapse"), "descrição:");
            if (strpos($title, 'pat') !== false) {
                return rtrim(Format::getExText($title, 'pat', 0), ".");
            }
            return $title;
        }
        return Format::getFilterText($this->node, "span.name");
    }

    // SET CATEGORY
    public function category() {
        if($this->info['category_id'] == "bens diversos") {
            $categoryStr = Format::getFilterText($this->node, "span.name");

            if (preg_match("/(ferramentas|utensilios)/i", $categoryStr)) {
                return 9;
            }
            if (preg_match("/(ferramentas)/i", $categoryStr)) {
                return 9;
            }
        }
        return Format::setCategory($categoryStr);
    }

    // SET SUB CATEGORY
    public function sub_category() {
        $stbcategoStr = Format::getFilterText($this->node, "span.name");

        switch ($this->category) {
            // PROPERTIES
            case 1:
                $property = Format::verifyPropertySubCategory($stbcategoStr);
                if(!$property){
                    $property = 1;
                }
                return $property;
            //VEHICLE
            case 2:
                $vehicle = Format::verifyVehicleByBrand($stbcategoStr);
                if(!$vehicle){
                    $vehicle = Format::verifyVehicleSubCategory($stbcategoStr);
                }
                if(!$vehicle){
                    $vehicle = 5;
                }
                return $vehicle;
            // INDUSTRY
            case 7:
                $industry = Format::verifyIndustryMachine($stbcategoStr);
                if(!$industry){
                    $industry = 23;
                }
                return $industry;
            // AGRO
            case 8:
                $agro = Format::verifyAgroMachine($stbcategoStr);
                if(!$agro){
                    $agro = 28;
                }
                return $agro;
            default:
                return null;
        }
    }

    // IS VALID AUCTION
    public function isValidAuction()
    {
        $text = Format::getFilterText($this->node, "div.text");
        if (preg_match("/(encerrado)/i", $text)) {
            return false;
        }

        if(! $this->innerNode){
            return false;
        }

        return true;
    }

    // SET FEATURED IMAGE
    public function featured_image(){
        return $this->node->filter('img')->attr('src');
    }

    // SET FEATURED IMAGE
    public function type(){
        return "judicial";
    }

    // SET CODE
    public function code(){
        return Format::getExText($this->link, '/', 'last');
    }

    // SET MIN VALUE
    public function minimum_value(){
        $desc = Format::getFilterTextHtml($this->innerNode, 'div.bids-summary');
        $priceOne = Format::getPrice($desc);
        $priceOne = preg_match("/(span)/i", $priceOne) ? Format::getExText($priceOne,"</", 0) : $priceOne ;
        return [
            'minimum_value_first'  => $priceOne,
            'minimum_value_second' => null
        ];
    }

    // PRICE VALUATION
    public function  price_valuation(){
        return null;
    }

    // DISCOUNT
    public function  discount(){
        return null;
    }

    // SET DATES
    public function dates() {
        $str = Format::getExText(Format::getEqText($this->nodepgContent, 'ul.detail-item > li', 0), 'data', 1);
        $dateOne = $str ? Carbon::createFromFormat('d/m/Y H:i',Format::extractDate($str) . "00:00")->format('d/m/Y H:i') : null;
        return ['date_first' => $dateOne,
                'date_second'=> null ];
    }

    // STATUS AUCTION
    public function status_auction() {
        return "aberto";
    }

    // SET SALE TYPE
    public function sale_type(){
        return "is_auction";
    }

    // SET DATES
    public function locations() {
        $city = "brasília";
        $state = "DF";
        return  Format::getLocation($this->category, $this->auction, $city . "," . $state,  $this->info['auctioneer_id'], $city . "-".$state);
    }

}
