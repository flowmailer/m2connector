<?php
namespace Flowmailer\M2Connector\Helper\API;
	
	class FlowmailerAPI {
		private $authURL = 'https://login.flowmailer.net/oauth/token';
		private $baseURL = 'http://api.flowmailer.net';

//		private $authURL = 'https://login.flowmailer.local/oauth/token';
//		private $baseURL = 'http://api.flowmailer.local';

		private $apiVersion = '1.4';

		private $maxAttempts = 3;
		private $maxMultiAttempts = 10;
		private $multiMaxConcurrent = 10;

		private $authToken;
		private $authTime;

		private $channel;
		private $logger;

		function __construct($accountId, $clientId, $clientSecret) {
			$this->accountId = $accountId;
			$this->clientId = $clientId;
			$this->clientSecret = $clientSecret;
			
			$mh = curl_multi_init();
			curl_multi_setopt($mh, CURLMOPT_MAXCONNECTS, 10);
#			curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, 10);
			$this->curlMulti = $mh;

			$this->channel = curl_init();
		}
		
		function __destruct() {
			curl_multi_close($this->curlMulti);
		}

		public function setLogger($logger) {
			$this->logger = $logger;
		}

		public function log($text) {
			if($this->logger) {
				$this->logger->debug($text);
			} else {
				echo($text . "\r\n");
			}
		}
		
		private function parseHeaders($header) {
			$headers = array();
			$fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
			$responseCodeHeader = explode(' ', $fields[0]);
			if(isset($responseCodeHeader[1])) {
				$headers['ResponseCode'] = $responseCodeHeader[1];
			} else {
				$headers['ResponseCode'] = '000';
			}

			foreach($fields as $field) {
				if(preg_match('/([^:]+): (.+)/m', $field, $match)) {
					//$match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
					$match[1] = preg_replace_callback(
							'/(?<=^|[\x09\x20\x2D])./',
							function ($matches) {
								return strtoupper($matches[0]);
							},
							strtolower(trim($match[1])));

					if(isset($headers[$match[1]])) {
						$headers[$match[1]] = array($headers[$match[1]], $match[2]);
					} else {
						$headers[$match[1]] = trim($match[2]);
					}
				}
			}
			return $headers;
		}

		private function getToken() {
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			
			curl_setopt($ch, CURLOPT_URL, $this->authURL);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, 1);
			
			$headers = array (
				'Content-Type: application/x-www-form-urlencoded'
			);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$fields = array(
				'client_id' => $this->clientId,
				'client_secret' => $this->clientSecret,
				'grant_type' => 'client_credentials'
			);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));

			$response = curl_exec($ch);

			$return = array();
			$return['response'] = $response;

			$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$return['headers'] = $this->parseHeaders(substr($return['response'], 0, $headerSize));
			$return['auth'] = json_decode(substr($return['response'], $headerSize));

			curl_close($ch);

			if($return['headers']['ResponseCode'] == 200) {
				return $return;
			} else {
				$authToken = null;
				$this->log($response);
				return false;
			}
		}
		
		private function ensureToken() {
			if($this->authToken === null || $this->authTime <= (time() - 30)) {
				$success = false;
				$attempts = 0;
				do {
					$attempts++;
					$response = $this->getToken();
					if($response !== false) {
						$success = true;
						$this->authTime = time() + $response['auth']->expires_in;
						$this->authToken = $response['auth']->access_token;
					}
				} while(!$success && $attempts < $this->maxAttempts);
			}
		}

		private function refreshToken() {
			$success = false;
			$attempts = 0;
			do {
				$attempts++;
				$response = $this->getToken();
				if($response !== false) {
					$success = true;
					$this->authTime = time() + $response['auth']->expires_in;
					$this->authToken = $response['auth']->access_token;
				}
			} while(!$success && $attempts < $this->maxAttempts);
		}

		private function curlExecWithMulti($handle) {
			curl_multi_add_handle($this->curlMulti, $handle);
			
			$running = 0;
			do {
				curl_multi_exec($this->curlMulti, $running);
				curl_multi_select($this->curlMulti);
			} while($running > 0);

			$output = curl_multi_getcontent($handle);
			curl_multi_remove_handle($this->curlMulti, $handle);
			return $output;
		}

		private function curlExecArrayWithMulti($handles) {
			
#			foreach($handles as $handle) {
#				curl_multi_add_handle($this->curlMulti, $handle);
#			}

#// http://technosophos.com/2012/10/26/php-and-curlmultiexec.html
#$active = NULL;
#do {
#    $mrc = curl_multi_exec($this->curlMulti, $active);
#print("mrc: " . $mrc . " " . $active . " " . CURLM_CALL_MULTI_PERFORM . "\r\n");
#} while ($mrc == CURLM_CALL_MULTI_PERFORM);
#
#
##while ($active && $mrc == CURLM_OK) {
###print("active: " . $mrc . " " . $active . "\r\n");
##    if (($selected = curl_multi_select($this->curlMulti, 1)) != -1) {
##print("selecteddd: " . $mrc . " " . $active . "\r\n");
##        do {
##            $mrc = curl_multi_exec($this->curlMulti, $active);
##print("mrc2: " . $mrc . " " . $active . "\r\n");
##        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
##    }
##
##	print("selected: " . $selected . "\r\n");
##}
##
##print("$active: " . $active . "\r\n");
			

			$todohandles = $handles; // dit maakt een kopie in php....wth?

			$running = NULL;
			do {
				$r2 = curl_multi_select($this->curlMulti);
				$r1 = curl_multi_exec($this->curlMulti, $running);
#				print("running: " . $running . " " . $r1 . " " . $r2 . "\r\n" );
#				print_r(curl_multi_info_read($this->curlMulti));
#				print_r(curl_multi_errno($this->curlMulti));

				while($running < $this->multiMaxConcurrent && count($todohandles) > 0) {
					$handle = array_pop($todohandles);
#					var_dump(count($todohandles));
					curl_multi_add_handle($this->curlMulti, $handle);
					$running++;
				}

			} while($running > 0 || count($todohandles) > 0);

#				print_r(curl_multi_info_read($this->curlMulti));


			$outputs = array();
			foreach($handles as $handle) {
#				print_r($handle);

				$output = curl_multi_getcontent($handle);
#var_dump($output);
				$outputs[] = $output;
				curl_multi_remove_handle($this->curlMulti, $handle);
			}

			return $outputs;
		}

		private function tryCall($uri, $expectedCode, $extraHeaders = null, $method = 'GET', $postData = null) {
			$ch = $this->channel;
			curl_setopt($ch, CURLOPT_URL, $this->baseURL . '/' . $this->accountId . '/' . $uri);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, 1);

			if($method == 'POST') {
				curl_setopt($ch, CURLOPT_POST, 1);
				if($postData != null) {
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
				}
			}

			$headers = array (
				'Connection: Keep-Alive',
				'Keep-Alive: 300',
				'Authorization: Bearer ' . $this->authToken,
				'Content-Type: application/vnd.flowmailer.v' . $this->apiVersion . '+json;charset=UTF-8',
				'Accept: application/vnd.flowmailer.v' . $this->apiVersion . '+json;charset=UTF-8',
				'Expect:'
			);
			
			if($extraHeaders !== null) {
				$headers = array_merge($headers, $extraHeaders);
			}
			
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$return = array();
			$return['response'] = $this->curlExecWithMulti($ch);
			//$return['response'] = curl_exec($ch);
			if($return['response'] === false) {
				$this->log('cURL returned false: ' . print_r(curl_getinfo($ch), true));
			}

			$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$return['headers'] = $this->parseHeaders(substr($return['response'], 0, $headerSize));
			
			$return['data'] = json_decode(substr($return['response'], $headerSize));
			
			//curl_close($ch);

			return $return;
		}

		private function tryMultiCall($uri, $expectedCodes, $extraHeaders = null, $method = 'GET', $postDataArray = null) {
			$channels = array();			
			foreach($postDataArray as $postData) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $this->baseURL . '/' . $this->accountId . '/' . $uri);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_HEADER, 1);

				if($method == 'POST') {
					curl_setopt($ch, CURLOPT_POST, 1);
					if($postData != null) {
						curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
					}
				}

				$headers = array (
					'Connection: Keep-Alive',
					'Keep-Alive: 300',
					'Authorization: Bearer ' . $this->authToken,
					'Content-Type: application/vnd.flowmailer.v' . $this->apiVersion . '+json;charset=UTF-8',
					'Accept: application/vnd.flowmailer.v' . $this->apiVersion . '+json;charset=UTF-8',
					'Expect:'
				);
			
				if($extraHeaders !== null) {
					$headers = array_merge($headers, $extraHeaders);
				}
			
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

				$channels[] = $ch;
			}

			$outputs = $this->curlExecArrayWithMulti($channels);
			$returns = array();
			foreach($outputs as $output) {
				$return = array();
				$return['response'] = $output;

				if($return['response'] === false) {
					$this->log('cURL returned false: ' . print_r(curl_getinfo($ch), true));
				}

				$parts = explode("\r\n\r\n", $return['response'], 2);
				$return['headers'] = $this->parseHeaders($parts[0]);
			
				if(isset($parts[1])) {
					$return['data'] = json_decode($parts[1]);
				}

				$returns[] = $return;
			}

			return $returns;
		}

		public function call($uri, $expectedCode, $headers = null, $method = 'GET', $postData = null) {
			$this->ensureToken();
			
			$success = false;
			$attempts = 0;
			do {
				$attempts++;
				$return = $this->tryCall($uri, $expectedCode, $headers, $method, $postData);
				if($return['headers']['ResponseCode'] == $expectedCode) {
					$success = true;
					return $return;
				}

				if($return['headers']['ResponseCode'] == 401) {
					$this->refreshToken();
					continue;
				}
				
				$this->log('retrying: ' . print_r($return, true));
				sleep(1);
			} while(!$success && $attempts < $this->maxAttempts);

			return $return;
		}

		public function multiCall($uri, $expectedCodes, $headers = null, $method = 'GET', $postDataArray = null) {
			$this->ensureToken();
			
#			$returns = $this->tryMultiCall($uri, $expectedCode, $headers, $method, $postDataArray);
#			foreach($returns as $return) {
#				print_r($return);
#				if($return['headers']['ResponseCode'] != $expectedCode) {
#					print_r($return);
#				}
#			}

			$indexes = range(0, count($postDataArray)-1);

			$attempts = 0;
			$allreturns = array();
			do {
#				print_r($indexes);
				$attempts++;

				$returns = $this->tryMultiCall($uri, $expectedCodes, $headers, $method, $postDataArray);

				$returnmap = array_map(null, $postDataArray, $returns, $indexes);
				$postDataArray = array();
				$indexes = array();
				$refreshToken = false;
				foreach($returnmap as list($postData, $return, $index)) {
#					print_r($postData);
#					print_r($return);

					if(in_array($return['headers']['ResponseCode'], $expectedCodes)) {
						$allreturns[$index] = $return;

					} else {
						$this->log('return: ' . print_r($return, true));

						$allreturns[$index] = $return;

						$postDataArray[] = $postData;
						$indexes[] = $index;
					}
	
					if($return['headers']['ResponseCode'] == 401) {
						$refreshToken = true;
					}
				}

				if(empty($postDataArray)) {
#					print("done: " . count($allreturns) . "\r\n");
					return $allreturns;
				}

				$this->log("retrying: " . count($postDataArray));

				if($refreshToken) {
					$this->refreshToken();
					continue;
				}
					
				sleep(1);

			} while($attempts < $this->maxMultiAttempts);
		}
		
		public function listCall($uri, $offset, $batchSize) {
			$lower = $offset;
			$upper = $offset + $batchSize;
			
			$headers = array(
				'range: items=' . $lower . '-' . $upper
			);
			
			return $this->call($uri, 206, $headers);
		}
		
		public function submitMessage(SubmitMessage $message) {
			return $this->call('/messages/submit', 201, null, 'POST', $message);
		}

		public function submitMessageList(array $messages) {
			return $this->multiCall('/messages/submit', array(201), null, 'POST', $messages);
		}

		public function submitDirtyMessageList(array $messages) {
			return $this->multiCall('/messages/submit', array(201,400), null, 'POST', $messages);
		}
		
		public function undeliveredMessages(DateTime $receivedFrom, DateTime $receivedTo, $addEvents = false, $addHeaders = false, $addOnlineLink = false) {
			$uri  = '/undeliveredmessages';
			
			$dateFrom = clone $receivedFrom;
			$dateFrom = $dateFrom->modify('-7 day'); //bounces can be received a week after sending
			$uri .= ';daterange=' . $dateFrom->format(DATE_ISO8601) . ',' . $receivedTo->format(DATE_ISO8601);
			$uri .= ';receivedrange=' . $receivedFrom->format(DATE_ISO8601) . ',' . $receivedTo->format(DATE_ISO8601);
			$uri .= '?addheaders=' . ($addHeaders ? 'true' : 'false');
			$uri .= '&addevents=' . ($addEvents ? 'true' : 'false');
			$uri .= '&addonlinelink=' . ($addOnlineLink ? 'true' : 'false');
			
			$done = false;
			$offset = 0;
			$batchSize = 100;
			
			$result = array();
			while(!$done) {
				$newResult = $this->listCall($uri, $offset, $batchSize);
				$result = array_merge($result, $newResult['data']);
				$offset += $batchSize;
				
				if(count($newResult['data']) < $batchSize) {
					$done = true;
				}
			}
			
			return $result;
		}


		public function messages(DateTime $submittedFrom, DateTime $submittedTo, $addEvents = false, $addHeaders = false, $addOnlineLink = false) {
			$uri  = '/messages';			
			$uri .= ';daterange=' . $submittedFrom->format(DATE_ISO8601) . ',' . $submittedTo->format(DATE_ISO8601);
			$uri .= '?addheaders=' . ($addHeaders ? 'true' : 'false');
			$uri .= '&addevents=' . ($addEvents ? 'true' : 'false');
			$uri .= '&addonlinelink=' . ($addOnlineLink ? 'true' : 'false');
			
			$done = false;
			$offset = 0;
			$batchSize = 100;
			
			$result = array();
			while(!$done) {
				$this->log($offset . ' ');
				$newResult = $this->listCall($uri, $offset, $batchSize);
				
				if(!is_array($newResult['data']) || count($newResult['data']) < $batchSize) {
					$done = true;
				}

				if(is_array($newResult['data'])) {
					$result = array_merge($result, $newResult['data']);
				}
				$offset += $batchSize;
			}
			
			return $result;
		}

		public function messageEvents(DateTime $receivedFrom, DateTime $receivedTo) {
			$uri  = '/message_events';
			$uri .= ';receivedrange=' . $receivedFrom->format(DATE_ISO8601) . ',' . $receivedTo->format(DATE_ISO8601);
			
			$done = false;
			$offset = 0;
			$batchSize = 100;
			
			$result = array();
			while(!$done) {
				$newResult = $this->listCall($uri, $offset, $batchSize);
				$result = array_merge($result, $newResult['data']);
				$offset += $batchSize;
				
				if(count($newResult['data']) < $batchSize) {
					$done = true;
				}
			}
			
			return $result;
		}

		public function message($messageId) {
			return $this->call('/messages/' . $messageId, 200, null, 'GET');
		}
	}
?>
