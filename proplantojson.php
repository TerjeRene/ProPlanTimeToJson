<?php
	ini_set("display_errors" , "0";
	/*
	CONSTANTS
	*/
	$protocol_domain = "http://timer.example.no/";
	$logon_user = "username";
	$logon_pass = "secret";
	$cache_time = 60 * 5; // 5 hours

	/*
	Based on example from php.net:
		Generic function to fetch all input tags (name and value) on a page
	*/
    function get_input_tags($html)
    {
        $post_data = array();

        // a new dom object
        $dom = new DomDocument;

        // do not need to validate
		$dom->validateOnParse = false;

        // load the html into the object, ignore any errors
        @$dom->loadHTML($html);

        // discard white space
        $dom->preserveWhiteSpace = false;

        // all input tags as a list
        $input_tags = $dom->getElementsByTagName('input');

        // get all rows from the table
        for ($i = 0; $i < $input_tags->length; $i++)
        {
            if( is_object($input_tags->item($i)) )
            {
                $name = $value = '';
                $name_o = $input_tags->item($i)->attributes->getNamedItem('name');
                if(is_object($name_o))
                {
                    $name = $name_o->value;

                    $value_o = $input_tags->item($i)->attributes->getNamedItem('value');
                    if(is_object($value_o))
                    {
                        $value = $input_tags->item($i)->attributes->getNamedItem('value')->value;
                    }

                    $post_data[$name] = $value;
                }
            }
        }

        return $post_data;
    }

    /*
    Taken from http://stackoverflow.com/a/6228666
    */
	function innerHTML($element) {
			$innerHTML = "";
			$children = $element->childNodes; $tmp_dom = new DOMDocument();
			foreach ($children as $child)
			{
				$tmp_dom->appendChild($tmp_dom->importNode($child, true));
			}
			unset ($child);
			$innerHTML.=trim($tmp_dom->saveHTML());
			return $innerHTML;
		}

	function talkToProplan() {
		$cookie_file_path = "cookie";
		$loginurl = protocol_domain . "Login.aspx?ReturnUrl=%2f";
		$agent = "Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.4) Gecko/20030624 Netscape/7.1 (ax)";

        // visit login page to get correct cookies and input tags
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$loginurl);
		curl_setopt($ch, CURLOPT_USERAGENT, $agent);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file_path);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
		$result = curl_exec ($ch);
		curl_close ($ch);

        // get and manipulate input tags from login.aspx
		$postData = get_input_tags($result);
		$postData['ctl00$ScriptManager'] = "";
		$postData['ctl00$cphMainPlaceHolder$logLogin$UserName'] = $logon_user;
		$postData['ctl00$cphMainPlaceHolder$logLogin$Password'] = $logon_pass;
		$postData['ctl00$cphMainPlaceHolder$ddlLocale'] = "3";

        // login.aspx is also handling the post. post our data to log in, allow redirect to main page
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$loginurl);
		curl_setopt($ch, CURLOPT_USERAGENT, $agent);
		curl_setopt($ch, CURLOPT_POST, count($postData));
		curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($postData));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_REFERER, $loginurl);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file_path);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$result = curl_exec ($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // for debug
		curl_close ($ch);

		// a new dom object
		$dom = new DomDocument();

		// load the html into the object, ignore errors
		@$dom->loadHTML($result);

		// discard white space
		$dom->preserveWhiteSpace = false;

        // collect data and arrange as we see fit
		$json = array(
			"flex_balance" => innerHTML($dom->getElementById('ctl00_cphMainPlaceHolder_wucMyAbsence_celFlexBalance')),
			"holidays" => array(
                "used" => innerHTML($dom->getElementById('ctl00_cphMainPlaceHolder_wucMyAbsence_celHolidaysUsed')),
                "planned" => innerHTML($dom->getElementById('ctl00_cphMainPlaceHolder_wucMyAbsence_celHolidaysPlanned')),
                "remaining" => innerHTML($dom->getElementById('ctl00_cphMainPlaceHolder_wucMyAbsence_celHolidaysRemaining')),
			),
			"abs_children" => innerHTML($dom->getElementById('ctl00_cphMainPlaceHolder_wucMyAbsence_celSelfCertifiedAbsenceChildren')),
			"hours" => array (
				"current" => innerHTML($dom->getElementById('ctl00_cphMainPlaceHolder_wucMyYearRate_cellThisPeriod')),
				"total" => innerHTML($dom->getElementById('ctl00_cphMainPlaceHolder_wucMyYearRate_cellTotalHours'))
			),
			"_lastRetrived" => (new DateTime())->format('Y-m-d H:i:s'),
			"_httpcode" => $httpcode,
			"ok" => true
		);
		return json_encode($json);
	}

	// Simple file cache
	if (file_exists($cache_file) && (filemtime($cache_file) > (time() - 60 * $cache_time ))) {
	   $output = file_get_contents($cache_file);
	}
	else {
		$output = talkToProplan();
		file_put_contents($cache_file, $output);
	}
	echo $output;
?>