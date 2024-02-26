<?php
$selectQuery = "
    SELECT u.user_id, u.user_email, i.document_id, d.document_uri, ds.sign_date, i.invite_hash, d.document_checksum, d.document_title, ec.document_id as signed_id 
    FROM vasa_esign_users u 
    INNER JOIN vasa_esign_invitations i ON u.user_id = i.user_id 
    INNER JOIN vasa_esign_documents d ON i.document_id = d.document_id 
    INNER JOIN vasa_esign_documents_signatures ds ON d.document_id = ds.document_id 
    LEFT JOIN vasa_esign_cron ec ON ec.document_id = d.document_id
    WHERE d.document_status = 'signed' AND ds.signature_id != 1 AND ec.document_id IS NULL
    ORDER BY ds.`sign_date` DESC
";
$result = $wpdb->get_results($selectQuery, ARRAY_A);

///////////////////////////////////////////////////////////////////////////////////////loop each form/document type  
$form_names = ['silver', 'redeem-gift', 'media-consent', 'business-waiver'];    //ADD new forms here    
$update_arr = [];
foreach($form_names as $form_name){
   
    $send_email = $admin_email;
    
    ///////////////////////////////////////////////////////////////////////////////////////loop_through_each_agreement  
    
    foreach($result as $agreement){
        
        /**************************
        * get the submission from the email, and then get all the other fields from that submission using submission_time
        * https://stackoverflow.com/questions/17892762/mysql-this-version-of-mysql-doesnt-yet-support-limit-in-all-any-some-subqu
        ***************************/
        $form_submission_query_name = 'Insurance Silver Sneaker Form';
        $form_submission_query = "SELECT submission_id FROM vasa_cf7_db_entries WHERE form_name = '" . $form_submission_query_name . "' AND email = %s ORDER BY submission_time DESC LIMIT 1";

        $selectFormQuery = "
        SELECT u.submission_id, u.email, u.form_values, u.submission_time
        FROM vasa_cf7_db_entries u 
        INNER JOIN ( " . $form_submission_query . ") as u2
            ON u.submission_id = u2.submission_id;
        ";
        $queryForm = $wpdb->prepare($selectFormQuery, $agreement['user_email']); 
        $resultForm = $wpdb->get_results($queryForm, ARRAY_A);

        /**************************
        * Create PDF
        ***************************/
        $esig = new ESIG_PDF_Admin();
        $document_id_num = (int)$agreement['document_id'];
        $current_error_reporting = error_reporting(0);//Start ApproveMe error suppression (their code is chock full of errors, but somehow still works)
        $pdf = $esig->pdf_document($document_id_num);//this is not the checksum ID, it is the document ID.  This is the E-sign/Approve me code to create a PDF.
        $upload_dir = wp_upload_dir();
        $pdf_type_base = $form_name == 'silver' ? "/signed-silver-docs/" : "/signed-waiver-docs/";
        $pdf_path = $upload_dir['basedir'] . $pdf_type_base . $agreement['document_checksum'] . '.pdf';
        file_put_contents($pdf_path,$pdf);
        error_reporting($current_error_reporting);
        @ini_set('display_errors', 0);//End Approveme error code suppression. They suppress them, so I am too. Ugly, but a bazillion uncontrollable errors is uglier.
        
        $user_arr = !empty($resultForm) ? $resultForm[0]['form_values'] : '';
        $user_arr =  !empty($user_arr) ? (array) json_decode($user_arr) : [];
        
        
        /**************************
        * Prep fields
        ***************************/
        $first_name =  $user_arr['ins-first-name'] ?: $user_arr['first-name'];
        $last_name = $user_arr['ins-last-name'] ?: $user_arr['last-name'];
        $email = $user_arr['ins-email'] ?: $user_arr['email'];
        $phone = $user_arr['ins-phone'] ?: $user_arr['phone'];
        $dob = $user_arr['ins-dob'] ?: $user_arr['dob'];
        $gender = $user_arr['male-female-other'];
        $address1 = $user_arr['ins-address-1'] ?: $user_arr['address-1'];
        $address2 = $user_arr['ins-address-2'] ?: $user_arr['address-2'];
        $city = $user_arr['ins-city'] ?: $user_arr['city'];
        $state = $user_arr['ins-state'] ?: $user_arr['state'];
        $zip = $user_arr['ins-zipcode'] ?: $user_arr['zip'];
        $pac_id = $user_arr['location-pac-id'] ?: 6228;
        $pac_id = $use_guts_instead_of_location_for_testing == true ? 'GUTS' : $pac_id;
        $lead_source_id = $form_name == 'silver' ? 6809 : 19895;
        $emergency_contact = $user_arr['ins-emergency-contact'] ?: $user_arr['emergency-contact'];
        $emergency_phone = $user_arr['ins-emergency-contact-phone'] ?: $user_arr['emergency-contact-phone'];
        $opt_in_for_sms = isset($user_arr['sms-agree']) && $user_arr['sms-agree'] ? true : false;
        
        $user_fields = [
           'first' => $first_name, 
           'last' => $last_name, 
           'email' => $email,
           'phone' => $phone, 
           'clubId' => $pac_id,
           'leadSourceId'  => $lead_source_id,
           'optInForSMS'  => $opt_in_for_sms, 
           'birthdate' => $dob, 
           'address1'  => $address1, 
           'address2'  => $address2, 
           'city' => $city, 
           'state' => $state, 
           'zipcode' => $zip
       ];
       
        /**************************
        * Fetch Member/Propsect
        ***************************/
        $stripped_phone = str_replace(' ', '', $phone);
        $member_num = 0;
        
        if ( $form_name == 'business-waiver' ) {
            $member_num = $user_fields['memberNumber'];
            if(empty($member_num)){ //if empty member number, try to receive via phone
                $fetched_member_response = fetch_member($stripped_phone, $pac_id);
                $fetched_member = isset($fetched_member_response['response']) && count($fetched_member_response['response']) > 0 ? $fetched_member_response['response'][0] : null;
                $member_num = !empty($fetched_member) ? $fetched_member['MembNum'] : 0;
            }
            
        }

      


        if( !empty($member_num) ){

            /**************************
            * Send Contract to PAC
            ***************************/
            $file_name = "Signed Insurance Agreement";

            $data = array(
                'FileName'        => $file_name,
                'ImageType'       => "pdf",
                'ScanType'        => "CLUB",
                'ScanLocation'    => "CLUB",
                'ImageBytes'      => base64_encode($pdf)
            );
           
        
            $curl = curl_init();//$memberships
        
            $data_string = json_encode( $data );
            $member_documents_url = get_field('member_documents_url','option'); 
            $url = $member_documents_url.$pac_id.'/'.$member_num.'/Documents';

            curl_setopt_array($curl, array(
              CURLOPT_URL => $url,
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
                "Authorization: Bearer 1F97F024-32B0-4C47-82ED-EE83B8174AFA",
                "Cache-Control: no-cache",
                "ClubAt: {$user_arr['location-pac-id']}",
                "Connection: keep-alive",
                "Content-Type: application/json",
                "DBLocation: PED",
                "UserId: 9B6F46F361",
                "Host: pacapi.webfdm.com",
                "api-version: 1",
                "cache-control: no-cache"
              )
            ));
        
            $doc_response['response'] = json_decode(curl_exec($curl), true);
            $doc_response['error'] = curl_error($curl);
            $doc_response['status'] = @curl_getinfo($curl, CURLINFO_HTTP_CODE); //Log the response status code
        
            curl_close($curl);

    		/**************************
		    * Update the vasa_esign_cron table so a submission doesn't process again
		    ***************************/
		    if(!empty($update_arr)){
		        $update_values = implode(',',$update_arr);
		    
		        $updateQuery = "
		            INSERT INTO vasa_esign_cron (`document_id`,`document_checksum`,`guest_id`,`document_name` )
		            VALUES $update_values;
		        ";
		        $update = $wpdb->get_results($updateQuery, ARRAY_A);        
		    } 

		}
	}
}

