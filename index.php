<?php
ini_set('html_errors', 1);

// Values we'll need through the process
$CLIENT_ID=htmlspecialchars("U0SQO6NXhnQQIbdENjza"); // Issued by FormAssembly host
$CLIENT_SECRET=htmlspecialchars("xCQhmQ3vex4lznU9Ozss"); // Issued by FormAssembly host
// Auto generate our return url for wherever this page is located.
$RETURN_URL= (!empty($_SERVER['HTTPS'])) ? "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] : "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
// Replace xxxxxx with correct url
$AUTH_ENDPOINT="https://app.formassembly.com/oauth/login"; 
// Replace xxxxxx with correct url
$TOKEN_REQUEST_ENDPOINT="https://app.formassembly.com/oauth/access_token";

// Set our API Endpoint
// https://server/api_v1/forms/index.xml
// Replace xxxxxx with correct url
$API_REQUEST="https://app.formassembly.com/api_v1/forms/index.json"; 



// If user ('Adam') is coming to page for the first time, generate the authorization url
// and redirect him to it.
if( empty($_GET) && empty($_POST)){
	$AUTH_URI="$AUTH_ENDPOINT?type=web&client_id=$CLIENT_ID&redirect_uri=$RETURN_URL&response_type=code";
// 	die('<pre>'.print_r(['server' => $_SERVER, 'auth_uri' => $AUTH_URI], true).'</pre>');
	header("Location: $AUTH_URI",TRUE,302);
}




// If user ('Adam') is returning from authorization endpoint, then parameter 'code' is on the
// the RETURN_URL value.  We will use it to make a (server-side) cURL request for the access_token.
$out_str = '';
if (!empty($_GET['code'])) {
    $CODE = $_GET['code'];
    
    $TOKEN_REQUEST_DATA=array("grant_type"=>"authorization_code",
        "type"=>"web_server",
        "client_id"=>$CLIENT_ID,
        "client_secret"=>$CLIENT_SECRET,
        "redirect_uri"=>$RETURN_URL,
        "code"=>$CODE
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $TOKEN_REQUEST_ENDPOINT);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,$TOKEN_REQUEST_DATA);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    $output = curl_exec($ch); 
    unset($ch);
    
    // Output from server is json formatted, PHP will turn it into an object for us.
    $TOKEN_REQUEST_RESPONSE = json_decode($output);
    $TOKEN = urlencode($TOKEN_REQUEST_RESPONSE->access_token);
    $out_str .= print_r($TOKEN_REQUEST_RESPONSE, true);
    
    // Build our API endpoint request with the token we've received.
    $FULL_API_REQUEST="$API_REQUEST?access_token=$TOKEN";
    
    // Make our server-side cURL call to the endpoint and get JSON back 
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $FULL_API_REQUEST);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    unset($ch);
    
    $forms_data = json_decode($API_RESPONSE = $output, true);

    $out_str .= "<pre>";
    $out_str .=  "\nURL: $FULL_API_REQUEST\n";
    $out_str .= print_r($forms_data, true);
    $out_str .= "</pre>";

    // Get responses for this form as csv
    $form = $forms_data['Forms'][0]['Form'];
    $RESPONSE_ENDPOINT = 'https://app.formassembly.com/api_v1/responses/export/'.$form['id'].'.csv';
    $out_str .= '<pre>'.$RESPONSE_ENDPOINT.'</pre>';
    
    $EXPORT_REQUEST = $RESPONSE_ENDPOINT.'?access_token='.$TOKEN;
    
    // Make our server-side cURL call to the endpoint and get CSV back 
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $EXPORT_REQUEST);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    unset($ch);

    $form_export = $output;//json_decode($output, true);
    $f = fopen($csv = getcwd().'/data/form_'.$form['id'].'_data.csv', 'w');
    // $out_str .= '<script type="text/javascript">console.log("'.$csv.'");</script>';
    fwrite($f, $form_export);
    fclose($f);
    
    $f = fopen($csv, 'r');
    
    $out_str .= '<h1>CSV Data</h1>';
    $headers = fgetcsv($f);
    $out_str .= '<table style="width: 100%;"><thead><caption>Form Data</caption><tr><th>';
    $out_str .= implode('</th><th>', $headers).'</th></tr></thead><tbody>';
    
    $current_client = $client_totals = $grand_total = null;
    while ($row = array_combine($headers, fgetcsv($f))) {
        // $out_str .= '<script type="text/javascript">console.log('.json_encode($row).');</script>';
        $out_str .= '<tr><td>'.implode('</td><td>', $row).'</td></tr>';
        $client_key = $row['Email'] ? sprintf('%s, %s <%s>', $row['Last Name'], $row['First Name'], $row['Email']) : $current_client;
        $current_client = $client_key;
        $row_total = $row['Amount'] === 'Other' ? $row['Other Amount'] : $row['Amount'];
        $row_total = floatval(preg_replace('/\D+/', '', $row_total));
        $client_totals[$client_key] += $row_total;
        $grand_total += $row_total;
    }
    $out_str .= '</tbody></table>';
    
    $top_five_clients = $client_totals;
    arsort($top_five_clients);
    $top_five_clients = array_slice($top_five_clients, 0, 5);
    
    $out_str .= '<pre>'.htmlspecialchars(print_r(['grand_total' => $grand_total, 'top_five_clients' => $top_five_clients, 'client_totals' => $client_totals], true)).'</pre>';

    // Get responses for this form as json
    // $form = $forms_data['Forms'][0]['Form'];
    // $RESPONSE_ENDPOINT = 'https://app.formassembly.com/api_v1/responses/export/'.$form['id'].'.json';
    // $out_str .= '<pre>'.$RESPONSE_ENDPOINT.'</pre>';
    
    // $EXPORT_REQUEST = $RESPONSE_ENDPOINT.'?access_token='.$TOKEN;
    
    // Make our server-side cURL call to the endpoint and get JSON back 
    // $ch = curl_init();
    // curl_setopt($ch, CURLOPT_URL, $EXPORT_REQUEST);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // $output = curl_exec($ch);
    // unset($ch);

    // $out_str .= '<script type="text/javascript">console.log('.$output.');</script>';
    // $form_export = json_decode($output, true);
    
    // $out_str .= '<pre>'.print_r($form_export, true).'</pre>';
    // foreach ($form_export['responses'] as $response) $out_str .= '<pre>'.print_r($response, true).'</pre>';
    
    $fdf_header = '%FDF-1.2
%,,oe"
1 0 obj
<<
/FDF << /Fields [';

    $fdf_footer = '"] >> >>
endobj
trailer
<</Root 1 0 R>>
%%EOF';
    
    $fdf_data = [
        'report_date' => date('n/j/y G:i:s A'),
        'total_amount' => '$'.number_format($grand_total, 2),
    ];
    
    $i = 0;
    foreach ($top_five_clients as $client_name => $client_amount) {
        $i++;
        $fdf_data['donor'.$i.'_name'] = $client_name;
        $fdf_data['donor'.$i.'_amount'] = '$'.number_format($client_amount, 2);
    }
    $out_str .= htmlspecialchars(print_r($fdf_data, true));
    
    // $fdf_content = $fdf_header;
    // foreach ($fdf_data as $key => $val) {
    //     $fdf_content .= '<</T('.$key.')/V('.$val.')>>';
    // }
    // $fdf_content .= $fdf_footer;
    
    // $out_str .= "<pre>\n".htmlspecialchars($fdf_content)."\n</pre>";
    
    // $fdf_path = tempnam(sys_get_temp_dir(), 'fdf');
    // $fdf_file = fopen($fdf_path, 'w');
    // fwrite($fdf_file, $fdf_content);
    // fclose($fdf_file);
    
    // $shell_cmd = 'pdftk data/vwdf.pdf fill_form '.$fdf_path;
    // $out_str .= "<pre>\n".$shell_cmd."\n</pre>";
    // passthru($shell_cmd);
    
    require('lib/fpdm/fpdm.php');

    $pdf = new FPDM('data/vwdf.pdf');
    $pdf->Load($fdf_data, false);
    $pdf->Merge();
    $pdf->Output();
    die();
}


?><!doctype html>
<html>
    <head>
        <title>FormAssembly API Demo</title>
    </head>
    <body>
        <h1>FormAssembly API Demo</h1>
        <pre>
            <?php
echo $out_str;
            ?>
        </pre>
    </body>
</html>
