<?php
function getSuggestionsInJSON($key){
    $curl = curl_init("http://localhost:8983/solr/csci572hw4/suggest?q=".$key);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
    $data = curl_exec($curl);

    curl_close($curl);
    return $data;
}

if (isset($_REQUEST['sug']) && !empty($_REQUEST['sug'])) {
    $last_w = $_REQUEST['sug'];
    $perf = '';

    if (strpos($_REQUEST['sug'],' ')  !== false){
        $sug_split = explode(" ", $_REQUEST['sug']);
        $last_w = array_pop($sug_split);
        $perf = implode(" " , $sug_split)." ";
        
    }
    
    if (empty($last_w)) {
        $last_w = trim($perf, " ");
        
    }

    $resArr = json_decode(getSuggestionsInJSON($last_w), true);

    $ans = array();
    $ans["suggestions"] = array();

    foreach ($resArr['suggest']['suggest'][$last_w]['suggestions'] as $terms){
        foreach ($terms as $field => $value){
            if ($field == 'term'){
                array_push($ans["suggestions"], array(
                    "value"     =>       $perf.$value,
                    "data"      =>       $perf.$value
                ));
            }
        }
    }

    echo json_encode($ans);
    exit;
}

ini_set("memory_limit", "4096M");

include 'SpellCorrector.php';
include 'simple_html_dom.php';

// make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html; charset=utf-8');
$limit = 10;
$query = isset($_REQUEST['q']) ? strtolower($_REQUEST['q']) : false; 
$results = False;


$temp = false;
if (isset($_REQUEST['temp']) && !empty($_REQUEST['temp'])) {
    if (intval($_REQUEST['temp']) == 1){
        $temp = true;
    }
} 


$page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
if ($page < 1) {
    $page = 1;
}


$file = fopen('/Users/daisy/solr-7.5.0/URLtoHTML_latimes.csv', 'r') or die("Unable to open file!");
$headers = fgetcsv($file);
$fileurlmap = array();

while (($line = fgetcsv($file,0,',')) !== FALSE) {

    $fileurlmap[$line[0]] = $line[1];

}

# error_log(var_dump($fileurlmap));
fclose($file);


$correct = $query;
$wrong = null;
// var_dump(SpellCorrector::correct("latises"));
$ary = explode(' ', $query);
$pointer = false;
$revised = '';
foreach($ary as $w){
    $r_word = $w;
    if ($w != SpellCorrector::correct($w)) {
        $pointer = True;
        $r_word = SpellCorrector::correct($w);
    }
    $revised .= $r_word;
    $revised .= ' ';
}


if ($pointer == True){
    $correct = $revised;
    $wrong = $query;
}

$current = $correct;
# if temp is true => current is wrong query else current is always correct answer
if ($temp and !is_null($wrong)){
    $current = $wrong;
}

if ($current) {
    
// The Apache Solr Client library should be on the include path // which is usually most easily accomplished by placing in the
// same directory as this script ( . or current directory is a default // php include path entry in the php.ini) 
require_once('./solr-php-client/Apache/Solr/Service.php');

// create a new solr service instance - host, port, and corename
// path (all defaults in this example)
    $solr = new Apache_Solr_Service('localhost', 8983, '/solr/csci572hw4');

// if magic quotes is enabled then stripslashes will be needed
    if (get_magic_quotes_gpc() == 1) {
        $current = stripslashes($current);
    }
// in production code you'll always want to use a try /catch for any // possible exceptions emitted by searching (i.e. connection
// problems or a query parsing error)
    // $choice = isset($_REQUEST['sort']) ? $_REQUEST['sort']:"Lucene";
    
    try
    {
        if($_REQUEST['sort'] == "Lucene"){
            $results = $solr->search($current, $page*10-10,10 );
        }
        else{
            $additionalParameters=array('sort' => 'pageRankFile desc');
            $results = $solr->search($current, $page*10-10,10, $additionalParameters);
        } 
    }
    catch (Exception $e)
    {
    // in production you'd probably log or email this error to an admin
    // and then show a special message to the user but for this example
    // we're going to show the full exception
        die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
    } 
}
?> 



<html>
    <head>
        <title>csci572 hw4</title>
        <link rel="stylesheet" href="http://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">

        <style>
        .autocomplete-suggestions { 
            border: 1px solid #999; 
            background: #F0F0F0; 
            overflow: auto; 
        }
        .autocomplete-selected { 
            background: #99CCFF; 
        }

        </style>
    </head>
     <body>
        <form accept-charset="utf-8" method="get" style="width:20%;margin:0px auto;">
            <label for="q" style = "display:block;">Query:</label>
            <input style = "width:200%" id="q" name="q" type="text" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>"/></br>
           
            <input type="radio" name="sort" value="pagerank" <?php if(isset($_REQUEST['sort']) && $_REQUEST['sort']== "pagerank") { echo "checked";} ?>>PageRank</br>           
            <input type="radio" name="sort" value="Lucene" <?php if(!isset($_REQUEST['sort']) || $_REQUEST['sort'] == "Lucene") { echo "checked";} ?>>Lucene</br>
            </br>
            <button type="submit">Submit</button>
        </form> 
        
        </br>
        </br>
        <?php
        // display results
        if ($results) 
        {
            $total = (int) $results->response->numFound; 
            $start = min($page*10-10+1, $total);
            $end = min($page*10, $total);
        ?>
        
       

        <div>
            <?php
            if (!is_null($wrong)){
                # wrong is not null=> need to revise
                # if temp is true, already click on the wrong answer with temp =1  ==> need to show did you mean right
                if (!$temp){
                    echo "Showing results for "."<a href='http://localhost/client.php?q={$current}&sort={$_REQUEST['sort']}' target='_blank'>".$current.'</a>'."<br>";
                    echo "Search instead for "."<a href='http://localhost/client.php?q={$wrong}&sort={$_REQUEST['sort']}&temp=1' target='_blank'>".$wrong.'</a>'."<br>"."<br>";
                
                }else{
                    echo "Did you mean "."<a href='http://localhost/client.php?q={$current}&sort={$_REQUEST['sort']}' target='_blank'>".$correct.'</a>'."<br>";
                }           
            }
            ?>

            Results <?php echo $start; ?> - <?php echo $end;?> of <?php echo $total; ?>:
            <button type="button" onclick="previousPage();">previous page</button>
            <button type="button" onclick="nextPage();">next page</button>
        </div>
        <ol> 
        
        <?php




        // iterate result documents

        foreach ($results->response->docs as $doc)
            { ?>
            <li>
            <table>
            <?php
            // iterate document fields / values
            $Id = "N/A";
            $Link="N/A";
            $Desc = "N/A";
            $Title = "N/A";
            // $check = "N/A";
            foreach ($doc as $field => $value){
                if($field == "og_url"){
                    if (is_array($value)){
                        $Link = $value[0];
                    }elseif($value){
                        $Link = $value;
                    }else{
                        $Link = $fileurlmap[trim(substr($Id,32))];
                    }
                    
                }
                if($field == "id"){
                    $Id = $value;
                }
                if($field == "title"){
                    if (is_array($value)){
                        $Title = $value[0];
                    }elseif($value){
                        $Title = $value;}
                }
                if($field == "description"){
                    // $check = gettype($value);
                    if (is_array($value)){
                        $Desc = $value[0];
                    }elseif($value){
                        $Desc = $value;}
                }
                

            }
            


            

             // The file_get_contents() reads a file into a string.
            $content_for_snip= file_get_contents($Id);
             // Create DOM from string then Dump contents (without tags) from HTML
            $content =  strtolower(str_get_html($content_for_snip)->plaintext);
          
            
            $snippet = "";
            $query_words= explode(" ", $query);

            $count = 0;
          

            foreach(preg_split("/((\r?\n)|(\r\n?))/", $content) as $line)
            {   
                
                if( strpos($line,$query) !== false){
                    $snippet = strtolower($line);
                    break;
                }


                foreach($query_words as $word )
                {
        
                    if(strpos(strtolower($line), strtolower($word)) !== false)
                    {
                        $count = $count+1;
                    }
                }

                if($count == sizeof($query_words))
                {
                    $snippet = strtolower($line);
                    break;
                }

                else if($count > 0)
                {
                    $snippet = strtolower($line);
                    break;
                }
                
                $count = 0;
                
            }
              
            if($snippet == ""){
                $snippet = $Desc;
            }
              

            $p = 0;
            $st = 0;
            $ed = 0;
            foreach($query_words as $word )
            {
                if (strpos(strtolower($snippet), strtolower($word)) !== false) 
                {
                    $p = strpos(strtolower($snippet), strtolower($word));
                    break;
                }
            }

            if($p > 80)
            {
                $st = $p - 80; 
            }

            $ed = $st + 160;

            if(strlen($snippet) < $ed)
            {
                $ed = strlen($snippet) - 1;
                $restrict_ed = "";
            }
            else
            {
                $restrict_ed = "....";
            }
            if(strlen($snippet) > 160)
            {
                if($st > 0){
                    $restrict_st = "....";
                }
                else{
                    $restrict_st = "";
                }
            }
            $snippet = $restrict_st.substr($snippet , $st , $ed - $st + 1).$restrict_ed;



        
            echo "<tr>";
            echo "Snippets: ".$snippet."<br>";		    
            echo "Title: ".'<a href='.$Link.'>'.$Title.'</a>'."<br>";
			echo "Link: ".'<a href='.$Link.' target="_blank">'.$Link.'</a>'."<br>";
			echo "ID: ".$Id."<br>";			
            echo "Description: ".$Desc."<br>";
            // echo '<a href='.$Link.' target="_blank">'.$Link.'</a>';
            
			echo "</tr>";

            ?>
            </table>
            </li> 
            <?php
            } ?>

        </ol> 
        <?php
        }?>


        <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
        <script src="./jquery.autocomplete.min.js"></script>
        <script>

        window.onload = function(){
            $('#q').autocomplete({
                serviceUrl: '/client.php',
                paramName: "sug",
                // minChars: 2,
                preventBadQueries: false,
                delimiter: '\s(\w+)$',
                onSelect: function (suggestion) {
                    console.log('You selected: ' + suggestion.value + ', ' + suggestion.data);
                }
            });
        }

        function previousPage() {
            // console.log('1');
            var url = window.location.href;
            // var temp_page = temp_url[-1];
            console.log(url)
            
            if (url.includes("page")){
                var temp_url = url.split('&')
                var temp_page = url.split('&')[temp_url.length-1];
                var n = parseInt(temp_page.split('=')[temp_page.split('=').length-1]);
                if (n >1){
                    page = n - 1;
                
                }else
                {
                    page = 1;
                }
                window.location= temp_url.slice(0, -1).join('&') + "&page=" + page;
            }else{
                window.location=window.location.href + "&page=1";
            }
        }

        function nextPage(){
            
            // console.log('1');
            var url = window.location.href;
            
            if (url.includes("page")){
                var temp_url = url.split('&')
                var temp_page = url.split('&')[temp_url.length-1];
                var n = parseInt(temp_page.split('=')[temp_page.split('=').length-1]) + 1
                window.location= temp_url.slice(0, -1).join('&') + "&page=" + n;
            }else{
                window.location=window.location.href + "&page=2";
            }
        }
        </script>
    </body> 
</html>

