<?php


namespace RobotsCommand;


use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use ClearAuctions;
use Format;
use ScrapParquedosLeiloeiros;
use Crawler;

class RobotParqueDosLeiloes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leilaomax:robotparquedosleiloes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Robot get content fot the auctioneer Nacional Leilões';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $paginationList = self::getPaginationData();
        foreach ($paginationList as $pageNumber => $page) {
            // GET CLIENT
            $client = new Client([
                                     'base_uri' => 'http://www.parquedosleiloes.com.br/',
                                     'http_errors' => false,  'timeout' => 60,'verify' => false
                                 ]);
            try {
                $html    = $client->request('GET', $page);
                $crawler = new Crawler();
                $crawler->addHtmlContent($html->getBody()->getContents(), 'UTF-8');

                $lotesCreated = $crawler->filter('div.card.auction-card')->each(
                    function (Crawler $node, $index) {
                        $category = Format::getFilterText($node, 'small.category');
                        $date = Format::getFilterText($node, 'small.date');
                        $dataStr = str_replace(',', ' ', Format::getDateMonthTextPrefix($date));
                        $dataStr = Carbon::createFromFormat('d/m/Y H:i', $dataStr)->format('d/m/Y H:i');
                        if( Carbon::parse(formatDateTime($dataStr))->toDateString() > Carbon::now()->toDateString() ){
                            return  ScrapParquedosLeiloeiros::process('Pq_leiloes_' . ( $index + 1 ),[$category, "search"], $node->filter('a')->attr('href'));
                        }
                    }
                );

                $list_created = $this->mergeArrayofArrays($lotesCreated);
                echo "\n\n COUNT ALL AUCTIONS -> " . count($list_created) . "\n";
                return ClearAuctions::process(54, $list_created);

            }
            catch (GuzzleHttp\Exception\ClientException $e) {
                return null;
            }
        }
    }

    public function getPaginationData(){
        $startTemp = "http://www.parquedosleiloes.com.br/leiloes?searchMode=normal&page=";
        $maxPage = 1;
        $i = 1;
        $list = [];
        while($i <= $maxPage){
            $page = $startTemp . $i;
            $client = new Client([
                                     'base_uri' => 'http://www.parquedosleiloes.com.br/',
                                     'http_errors' => false,  'timeout' => 60,'verify' => false
                                 ]);
            $html = $client->request('GET', $page);
            $crawler = new Crawler('' .$html->getBody());
            //$str = Format::getFilterText($crawler, 'div.auctions-cards.cards');
            $date = Format::getFilterText($crawler, 'small.date');
            $dataStr = str_replace(',', ' ', Format::getDateMonthTextPrefix($date));
            $dataStr = Carbon::createFromFormat('d/m/Y H:i', $dataStr)->format('d/m/Y H:i');
            if( Carbon::parse(formatDateTime($dataStr))->toDateString() > Carbon::now()->toDateString() ){
                array_push($list, $page);
            }
            $i++;
        }
        return $list;
    }

    public function mergeArrayofArrays($array, $property = null)
    {
        return array_reduce(
            (array) $array, // make sure this is an array too, or array_reduce is mad.
            function($carry, $item) use ($property) {

                $mergeOnProperty = (!$property) ?
                    $item :
                    (is_array($item) ? $item[$property] : $item->$property);

                return is_array($mergeOnProperty)
                    ? array_merge($carry, $mergeOnProperty)
                    : $carry;
            }, array()); // start the carry with empty array
    }
}
