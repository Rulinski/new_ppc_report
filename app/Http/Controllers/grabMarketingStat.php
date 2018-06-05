<?php

namespace App\Http\Controllers;

use App\GoogleSheet;
use Composer\IO\NullIO;
use Google;
use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\v201802\cm\CampaignService;
use Google\AdsApi\AdWords\v201802\cm\OrderBy;
use Google\AdsApi\AdWords\v201802\cm\Paging;
use Google\AdsApi\AdWords\v201802\cm\Selector;
use Google\AdsApi\AdWords\v201802\cm\SortOrder;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google_Client;
use Google_Service_Drive;
use Google\AdsApi\Common\ConfigurationLoader;

use Google\AdsApi\AdWords\Reporting\v201802\DownloadFormat;
use Google\AdsApi\AdWords\Reporting\v201802\ReportDefinition;
use Google\AdsApi\AdWords\Reporting\v201802\ReportDefinitionDateRangeType;
use Google\AdsApi\AdWords\Reporting\v201802\ReportDownloader;
use Google\AdsApi\AdWords\ReportSettingsBuilder;
use Google\AdsApi\AdWords\v201802\cm\Predicate;
use Google\AdsApi\AdWords\v201802\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201802\cm\ReportDefinitionReportType;
use Hamcrest\Core\DescribedAsTest;
use Happyr\LinkedIn;
use Google_Service_Sheets;
use Google_Service_Sheets_ClearValuesRequest;
use Google_Service_Sheets_ValueRange;
use Carbon\Carbon;
use App\Api\GoogleClient;
use Sheets;
use \App\GoogleSheet as Sheet;
use Illuminate\Http\Request;
use Exception;



class grabMarketingStat extends Controller
{

    const PAGE_LIMIT = 500;
    public $input = [];
    public $inputArbitary = [];
    public $message = '';

    public function get(Request $request)
    {
        try {
            $data = array();
            $bufer = explode('&', $request->getContent());
            $fields_form = ['type-report',
                'date-from',
                'date-to',
            ];

            foreach ($bufer as $fb) {
                foreach ($fields_form as $ff) {
                    if (stripos($fb, $ff) !== false)
                        $data[$ff] = str_replace($ff . '=', '', $fb);
                }
            }
            self::grab('main', $data['type-report'], $data['date-from'], $data['date-to']);
        }
        catch (Exception $e) {
        dd($e);
        return false;
        }
        return view('ppc.index', ["message" => $this->message]);
    }

    public function index(Request $request)
    {
        return view('ppc.index', ["message" => $this->message]);
    }

    public static function getReport(AdWordsSession $session, $reportQuery, $reportFormat)
    {

        // Download report as a string.
        $reportDownloader = new ReportDownloader($session);
        // Optional: If you need to adjust report settings just for this one
        // request, you can create and supply the settings override here. Otherwise,
        // default values from the configuration file (adsapi_php.ini) are used.
        $reportSettingsOverride = (new ReportSettingsBuilder())
            ->includeZeroImpressions(false)
            ->build();
        $reportDownloadResult = $reportDownloader->downloadReportWithAwql(
            $reportQuery, $reportFormat, $reportSettingsOverride);
        $stringResult = $reportDownloadResult->getAsString();
        return $stringResult;
    }


    public function getReportAdwords($customer_id, $compaign_id, $during, $arbitary)
    {
        ini_set("max_execution_time", 0);
        $OAuth2TokenBuilder = new OAuth2TokenBuilder();
        $configurationLoader = new ConfigurationLoader();
        $config = '[ADWORDS]
developerToken = "' . $_ENV['ADWORDS_DEVELOPER_TOKEN'] . '"
clientCustomerId = "' . $customer_id . '"
[OAUTH2]
 clientId = "' . $_ENV['ADWORDS_CLIENT_ID'] . '"
 clientSecret = "' . $_ENV['ADWORDS_CLIENT_SECRET'] . '"
 refreshToken = "' . $_ENV['ADWORDS_REFRESH_TOKEN'] . '"';

        $config_cool = sprintf($_ENV['ADWORDS_CONFIG'], $_ENV['ADWORDS_DEVELOPER_TOKEN'], $customer_id, $_ENV['ADWORDS_CLIENT_ID'], $_ENV['ADWORDS_CLIENT_SECRET'], $_ENV['ADWORDS_REFRESH_TOKEN']);

        $oAuth2Credential = ($OAuth2TokenBuilder->from($configurationLoader->fromString($config)))->build();

        $session = (new AdWordsSessionBuilder())->from($configurationLoader->fromString($config))->withOAuth2Credential($oAuth2Credential)->build();

        // Create report query
        $buildReportQuery = 'SELECT CampaignId, '
            . 'Clicks, Impressions, Cost, AllConversionValue FROM CRITERIA_PERFORMANCE_REPORT '
            . 'WHERE CampaignId IN [%s] DURING %s';
        $buildReportQuery = sprintf($buildReportQuery, $compaign_id, $during);

        $stringReport = self::getReport($session, $buildReportQuery, DownloadFormat::CSV);
        $arrayReport = explode(',', $stringReport);
        $Click = $arrayReport[count($arrayReport) - 4];
        $Impressions = $arrayReport[count($arrayReport) - 3];
        $Cost = $arrayReport[count($arrayReport) - 2];
        $Conversion = $arrayReport[count($arrayReport) - 1];

        //Cut of zeros
        if ($Cost != 0) {
            $Cost = (float)$Cost / 1000000;
        }

        //Switch input result to current or arbitary month

        if ($arbitary != 0) {
            array_push($this->inputArbitary, array($Click, $Impressions, $Cost, $Conversion));
        } else {
            array_push($this->input, array($Click, $Impressions, $Cost, $Conversion));
        }

        $this->message .= " successful! <br>";

    }

    public function grab($params, $report = Null, $date_from = Null, $date_to = Null)
    {
        try{
        ini_set("max_execution_time", 0);
        $default = [
            'APPLICATION_NAME' => 'Google Sheets API',
            'CREDENTIALS_PATH' => app_path() . '/ApiSources/google-sheets.json',
            'CLIENT_SECRET_PATH' => app_path() . '/ApiSources/client_secret_sheets.json',
            'SCOPES' => array(
                Google_Service_Sheets::SPREADSHEETS_READONLY,
            ),
        ];
            $client = GoogleClient::get_instance($default);
            $service = new Google_Service_Sheets($client->client);
            $spreadsheetId = '1Q4j81zbUXfi2trsiZORF0fGgx_cSFKN5uokJIZOwP0I';
            //get ranges of input
            switch ($params) {
                case 'clone':
                    $CurrentSheet = 'New Detail Raw data';
                    $urlSheet = "<a href='https://docs.google.com/spreadsheets/d/1Q4j81zbUXfi2trsiZORF0fGgx_cSFKN5uokJIZOwP0I/edit#gid=1774056017'>View result</a><br>";
                    break;
                case 'main':
                    $CurrentSheet = 'Raw data';
                    $urlSheet = "<a href='https://docs.google.com/spreadsheets/d/1Q4j81zbUXfi2trsiZORF0fGgx_cSFKN5uokJIZOwP0I/edit#gid=1311329247'>View result</a><br>";
                    break;
                default:
                    $this->message .= "Invalid URL ...";
                    exit();
                    break;
            }
            if (isset($report)) {
                switch ($report) {
                    case "1":
                        $CurrentSheet = 'New Detail Raw data';
                        $urlSheet = "<a href='https://docs.google.com/spreadsheets/d/1Q4j81zbUXfi2trsiZORF0fGgx_cSFKN5uokJIZOwP0I/edit#gid=1774056017'>View result</a><br>";
                        break;
                    case "0":
                        $CurrentSheet = 'Raw data';
                        $urlSheet = "<a href='https://docs.google.com/spreadsheets/d/1Q4j81zbUXfi2trsiZORF0fGgx_cSFKN5uokJIZOwP0I/edit#gid=1311329247'>View result</a><br>";
                        break;
                    default:
                        $this->message .= "Error ...";
                        exit();
                        break;
                }
            }
            $ranges = Sheet::getOfSheet($service, $spreadsheetId, $CurrentSheet .'!A3:H4');

            $rangeInputCurrent = $CurrentSheet . '!' . $ranges[0][1] . ':' . $ranges[0][2];
            $rangeInputLast = $CurrentSheet . '!' . $ranges[0][3] . ':' . $ranges[0][4];
            $rangeInputArbitary = $CurrentSheet . '!' . $ranges[0][5] . ':' . $ranges[0][6];
            $rangeSource = $CurrentSheet . '!' . $ranges[1][1] . ':' . $ranges[1][2];
            $rangeNameCurrent = $CurrentSheet . '!' . 'C8:F8';
            $rangeNameLast = $CurrentSheet . '!' . 'G8:J8';
            $rangeDateUpdated = $CurrentSheet . '!' . 'B5';
            $rangeArbitaryFrom = $CurrentSheet . '!' . 'L8';
            $rangeArbitaryTo = $CurrentSheet . '!' . 'M8';
            $ProcessingArbitary = isset($date_from) && !empty($date_from) && isset($date_to) && !empty($date_to) ? true : false;

            $duringCurrent = date('Ym') . '01, ' . date('Ymd'); // This month
            if ($ProcessingArbitary) {
                $duringArbitary = str_replace('-', '', $date_from) . ', ' . str_replace('-', '', $date_to); // Arbitary month
            }

            $source = Sheet::getOfSheet($service, $spreadsheetId, $rangeSource);
            $this->message .= "Processing started!<br>";
            foreach ($source as $row) {
                if (isset($row) && !empty($row)) {
                    if ($row[0] == "adwords") {
                        //get adwords account
                        $this->message .= "Adwords AccountID=" . isset($row[1]) && !empty($row[1]) ? $row[1] : 'Empty AccountID' . " - ";
                        $customer_id = str_replace('-', '', isset($row[1]) && !empty($row[1]) ? $row[1] : '');
                        if (isset($row[2]) && !empty($row[2])) {
                            $compaign_id = $row[2];
                            if (count($row) > 2) {
                                for ($i = 3; $i <= count($row); $i++) {
                                    if (isset($row[$i]) && !empty($row[$i])) {
                                        $compaign_id = $compaign_id . ', ' . $row[$i];
                                    } else break;
                                }
                            }
                            if ($ProcessingArbitary) {
                                self::getReportAdwords($customer_id, $compaign_id, $duringArbitary, 1);
                            } else {
                                self::getReportAdwords($customer_id, $compaign_id, $duringCurrent, 0);
                            }
                        } else {
                            if ($ProcessingArbitary) {
                                array_push($this->inputArbitary, array(0, 0, 0, 0));
                            } else {
                                array_push($this->input, array(0, 0, 0, 0));
                            }

                            $this->message .= " empty CompaignID! <br>";
                        }
                    } elseif ($row[0] == "bing") {
                        //get bing account
                        $this->message .= "Bing AccountID=" . isset($row[1]) && !empty($row[1]) ? $row[1] : 'Empty AccountID' . " - ";
                        if ($ProcessingArbitary) {
                            array_push($this->inputArbitary, array(0, 0, 0, 0));
                        } else {
                            array_push($this->input, array(0, 0, 0, 0));
                        }
                        $this->message .= " not processed! <br>";
                    } elseif ($row[0] == "linkedin") {
                        //get linkedin account
                        $this->message .= "Linkedin AccountID=" . isset($row[1]) && !empty($row[1]) ? $row[1] : 'Empty AccountID' . " - ";
                        if ($ProcessingArbitary) {
                            array_push($this->inputArbitary, array(0, 0, 0, 0));
                        } else {
                            array_push($this->input, array(0, 0, 0, 0));
                        }
                        $this->message .= " not processed! <br>";
                    }
                } else
                    //get empty row
                    if ($ProcessingArbitary) {
                        array_push($this->inputArbitary, array(NULL, NULL, NULL, NULL));
                    } else {
                        array_push($this->input, array(NULL, NULL, NULL, NULL));
                    }
            }
            $this->message .= "Processing complete!<br>";


            //Set result to Google Sheets


            if ($ProcessingArbitary) {
                //The arbitary month
                If (!Sheet::setToSheet($service, $spreadsheetId, $rangeInputArbitary, $this->inputArbitary)) {
                    $this->message .= "Error update range to Sheet!";
                } else {
                    Sheet::setToSheet($service, $spreadsheetId, $rangeArbitaryFrom, array(array(date("F j, Y", strtotime($date_from)))));
                    Sheet::setToSheet($service, $spreadsheetId, $rangeArbitaryTo, array(array(date("F j, Y", strtotime($date_to)))));
                    $this->message .= "Statistics for the arbitary month have been updated!<br>";
                }
            } else {
                //The current month
                If (!Sheet::setToSheet($service, $spreadsheetId, $rangeInputCurrent, $this->input)) {
                    $this->message .= "Error update range to Sheet!";
                } else {
                    $this->message .= "Statistics for the current month have been updated!<br>";
                    Sheet::setToSheet($service, $spreadsheetId, $rangeDateUpdated, array(array(date('r'))));
                    Sheet::setToSheet($service, $spreadsheetId, $rangeNameCurrent, array(array(date('F'))));
                };
                //The last month
                If (date('t') == date('d')) {
                    If (!Sheet::setToSheet($service, $spreadsheetId, $rangeInputLast, $this->input)) {
                        $this->message .= "Error update range to Sheet!";
                    } else {
                        $this->message .= "Statistics for the last month have been updated!<br>";
                        Sheet::setToSheet($service, $spreadsheetId, $rangeNameLast, array(array(date('F'))));
                    };
                }

            }
            $this->message .= $urlSheet;
    }
    catch (Exception $e) {
    dd($e);
    }
    }

    //**************LINKEDIN***********
    public function grabLinkedin()
    {
        // *************Login***********
        $linkedIn = new \Happyr\LinkedIn\LinkedIn('77dtkigf97865t', 'anVsVZjlXTj6Hcr6');
        $linkedIn->setHttpClient(new \Http\Adapter\Guzzle6\Client());
        $linkedIn->setHttpMessageFactory(new \Http\Message\MessageFactory\GuzzleMessageFactory());
        if ($linkedIn->isAuthenticated()) {
            //we know that the user is authenticated now. Start query the API
            $user = $linkedIn->get('v1/people/~:(firstName,lastName)');
            $compaign=$linkedIn->get('v2/adCampaignsV2/{500610594}');
            echo "Welcome " . $user['firstName'];
            echo "<pre>";
            var_dump($compaign);
            echo "</pre>";

            exit();
        } elseif ($linkedIn->hasError()) {
            echo "User canceled the login.";
            exit();
        }

        //if not authenticated
        $url = $linkedIn->getLoginUrl();
        echo "<a href='$url'>Login with LinkedIn</a>";


    }

    public function grabBing()
    {


    }

}
