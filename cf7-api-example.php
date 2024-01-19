<?php 

add_action( 'wpcf7_before_send_mail', 'form_processing', 15, 3 );
function form_processing( $contact_form, &$abort, $sub ) {
    $submission = WPCF7_Submission::get_instance();
    $posted_data = $submission->get_posted_data();
    is_error = false;
    error_message = '';
    
    $return_arr = submit_24_hour_form($posted_data);
    

    
    if(!empty($return_arr) && $return_arr['error']){
        $is_error = true;
        $error_message .= ' ' . ($return_arr['error_message'] ?? '');
    }
    
    if($is_error){  
        $abort = true;
        $sub->set_status('validation_failed');
        if(!empty($error_message)){
            $sub->set_response($error_message);
        }
    } 

}



/**************************************************************
* 24 Hour Waiver Agreement
**************************************************************/
function submit_24_hour_form($posted_data){
    $result = [];
    $is_error = false;
    $error_message = '';

    $member_search_url = get_field('members_search_by_barcode','option');
    $member_documents_url = get_field('member_documents_url','option');
    $tf_hour_waiver = get_field('24_hour_waiver_agreement', 'option');
    
    $curl = curl_init();

    $properties = [
        'SearchValue'   => $posted_data['tfh-barcode-id'],
        'Prefix'        => "BC",
        'MaxLimit'      => 10
    ];
    $data_string = json_encode( $properties );

    //use barcode to find user's member number and club for the member document API call
    curl_setopt_array($curl, array(
      CURLOPT_URL => $member_search_url, 
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => $data_string,
      CURLOPT_HTTPHEADER => array(
        "Accept: */*",
        "Accept-Encoding: gzip, deflate",
        "Authorization: Bearer {BEARER TOKEN}",
        "Cache-Control: no-cache",
        "Connection: keep-alive",
        "Content-Type: application/json",
        "api-version: 1",
        "cache-control: no-cache"
      )
    ));


    $response = curl_exec($curl);
    $err = curl_error($curl);
    $result['status_membersearch'] = @curl_getinfo($curl, CURLINFO_HTTP_CODE); //Log the response status code

    curl_close($curl);

    if ($err) {  
        $is_error = true;
        $result['error_membersearch'] = "cURL Error #:" . $err;
        $error_message = "Sorry, but the form failed to process.  Please try again in a few minutes.";
    } elseif($result['status_membersearch'] == '204') {//bad barcode, or no user data found
        $is_error = true;
        $error_message = "No user data found based on the provided barcode.  Please check the barcode for accuracy and try again.";
    }elseif($result['status_membersearch'] != '200'){
        $is_error = true;
        $error_message = "Sorry, but the form failed to process.  Please try again in a few minutes.";    
    }else{
        $result['response_membersearch'] = $response;

        $decoded_r = array_shift(json_decode($response, true));

        $data = "{$posted_data['tfh-first-name']} {$posted_data['tfh-last-name']}\n";
        $data .= "{$posted_data['tfh-barcode-id']}\n";
        $data .= "Submitted ".date('Y-m-d h:i:s A')."\n\n\n";
        $data .= $tf_hour_waiver;
        $encoded_data = base64_encode($data);

        $doc_properties = [
            'FileName'      => $posted_data['tfh-first-name'].' '.$posted_data['tfh-last-name'].' Liability Waiver & Member Agreement',
            'ImageType'     => 'txt',
            'ScanType'      => 'CLUB',
            'ScanLocation'  => 'CLUB',
            'ImageBytes'    => $encoded_data
        ];
        $doc_data_string = json_encode( $doc_properties );
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $member_documents_url.'/'.$decoded_r['ClubId'].'/'.$decoded_r['MembNum'].'/Documents',//"https://pacapi.webfdm.com/API/Members/6677/002370601W/Documents",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => $doc_data_string,
          CURLOPT_HTTPHEADER => array(
            "Accept: */*",
            "Accept-Encoding: gzip, deflate",
            "Authorization: Bearer {BEARER TOKEN}",
            "Cache-Control: no-cache",
            "Connection: keep-alive",
            "Content-Type: application/json",
            "api-version: 1",
            "cache-control: no-cache"
          )
        ));

        $response_doc = curl_exec($curl);
        $err_doc = curl_error($curl);
        $result['status_memberdocument'] = @curl_getinfo($curl, CURLINFO_HTTP_CODE); //Log the response status code

        if ($err_doc ) {
            $is_error = true;
            $result['error_membersdocument'] = "cURL Error #:" . $err_doc;
            $error_message = "Sorry, but the form failed to process.  Please try again in a few minutes.";
        } elseif($result['status_memberdocument'] != '200'){
            $is_error = true;
            $error_message = "Sorry, but the form failed to process.  Please try again in a few minutes.";
        }else {
            $result['response_membersdocument'] = $response_doc;
        }

    }

    curl_close($curl);

    
    return ['error' => $is_error, 'error_message' => $error_message];
}
