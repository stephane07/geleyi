<?php
/*
* Define class pspPageSpeedInsightsAjax
* Make sure you skip down to the end of this file, as there are a few
* lines of code that are very important.
*/
!defined('ABSPATH') and exit;
if (class_exists('pspPageSpeedInsightsAjax') != true) {
    class pspPageSpeedInsightsAjax extends pspDashboard
    {
    	public $the_plugin = null;
		private $module_folder = null;
		private $file_cache_directory = '/psp-page-speed';
		private $cache_lifetime = 60; // in seconds
		
		/*
        * Required __construct() function that initalizes the AA-Team Framework
        */
        public function __construct( $the_plugin=array() )
        {
        	$this->the_plugin = $the_plugin;
			$this->module_folder = $this->the_plugin->cfg['paths']['plugin_dir_url'] . 'modules/google_pagespeed/';
			
			// ajax  helper
			add_action('wp_ajax_pspPageSpeedInsightsRequest', array( &$this, 'ajax_request' ));
		}
		
		/*
		* ajax_request, method
		* --------------------
		*
		* this will create requests to 404 table
		*/
		public function ajax_request()
		{
			$return = array();
			$actions = isset($_REQUEST['sub_actions']) ? explode(",", $_REQUEST['sub_actions']) : '';
			
			if( in_array( 'checkPage', array_values($actions)) ){
				
				// get page id by URL
				$page_url = get_permalink( (int) $_REQUEST['id'] );
				
				$ret = $this->check_page( $page_url, (int) $_REQUEST['id'] );
				
				$return['checkPage'] = array(
					'status' => 'valid',
					'desktop_score' => $ret['desktop_score'],
					'mobile_score' => $ret['mobile_score']
				);
			}	
			 
			if( in_array( 'viewSpeedRaportById', array_values($actions)) ){
				$html = array();
				$page_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
				
				$upload_dir = wp_upload_dir();
				// get page id by URL
				$page_url = get_permalink( $page_id );
				
				// update the speed score
				$this->check_page( $page_url, $page_id );
				
				$speed_scores = array();
				
				$settings = $this->the_plugin->getAllSettings( 'array', 'pagespeed' );
				
				if( $settings['report_type'] == 'both' || $settings['report_type'] == 'desktop' ){
					$speed_scores['desktop'] = get_post_meta( $page_id, 'psp_desktop_pagespeed', true );  
				}
				
				if( $settings['report_type'] == 'both' || $settings['report_type'] == 'mobile' ){
					$speed_scores['mobile'] = get_post_meta( $page_id, 'psp_mobile_pagespeed', true );  
				}

				if(count($speed_scores) > 0 ){
					// write the header 
					$html[] = '<div class="psp-pagespeed-header">';
					
					$cc = 0;
					foreach ($speed_scores as $key_device => $value_device) {
						// normalize some vars 
						$stats = $value_device['pageStats'];
						
						$score = (int)$value_device['score'];
						$size_class = 'size_';
						if ( $score >= 20 && $score < 40 ){
							$size_class .= '20_40';
						}elseif ( $score >= 40 && $score < 60 ){
							$size_class .= '40_60';
						}
						elseif ( $score >= 60 && $score < 80 ){
							$size_class .= '60_80';
						}elseif ( $score >= 80 && $score <= 100 ){
							$size_class .= '80_100';
						}else{
							$size_class .= '0_20';
						}
						
						$html[] = 	'<div class="psp-tab-item ' . ( $cc == 0 ? 'on' : '' ) . '" data-rel="' . ( $key_device ) . '">';
						$html[] = 		'<table>';
						$html[] = 			'<tr>';
						$html[] = 				'<td>';
						$html[] = 				'<div class="psp-progress">';
						$html[] = 						'<div style="width:' . ( $score ) . '%" class="psp-progress-bar ' . ( $size_class ) . '"></div>';
						$html[] = 						'<div class="psp-progress-score">' . ( $score ) . '/100</div>';
						$html[] = 					'</div>';
						$html[] = 				'</td>';
						$html[] = 				'<td class="psp-tab-title">';
						$html[] = 					ucfirst($key_device);
						$html[] = 				'</td>';
						$html[] = 			'</tr>';
						$html[] = 		'</table>';
						$html[] = 	'</div>';
						
						$cc++;
					}
					
					
					$html[] = 	'<a href="#" class="psp-button red psp-close-page-detail"> ' . ( __('Close Page Report', $this->the_plugin->localizationName) ) . '</a>';
					
					$html[] = '</div>';
					
					$html[] = '<div class="psp-pagespeed-page-content">';
					$cc = 0;
					foreach ($speed_scores as $key_device => $value_device) {
						$img = '';
						if(is_file( $upload_dir['path'] . $this->file_cache_directory . '/' . $page_id . '-' . ( $key_device ) . '.jpg' )){
							$img = $upload_dir['url'] . $this->file_cache_directory . '/' . $page_id . '-' . ( $key_device ) . '.jpg';
						}
						
						// normalize some vars 
						$stats = $value_device['pageStats'];
						$rule_results = $value_device['formattedResults']['ruleResults'];
						
						$html[] = 	'<div id="psp-pagespeed-page-' . ( $key_device ) . '" class="psp-pagespeed-tab" style="' . ( $cc > 0 ? 'display:none' : '' ) . '">';
						$html[] = 		'<div class="left psp-report-rules">';
						$html[] = 			'<div class="psp-grid_4">';
						$html[] = 				'<div class="psp-panel">';
						$html[] = 					'<div class="psp-panel-header">';
						$html[] = 						'<div class="psp-panel-title">' . ( __('Suggestions Summary', $this->the_plugin->localizationName) ) .'</div>';
						$html[] = 					'</div>';
						$html[] = 				'<div class="psp-panel-content psp-summary-box">';
						$html[] = 					'<div class="psp-sub-panel-content">';
						$html[] = 						'<table class="psp-what-to-do">';
						
						if( count($rule_results) > 0 ){
							foreach ($rule_results as $key_rule => $value_rule) {
								
								//if( $key_rule != "MinimizeRenderBlockingResources") continue;
								
								$html[] = 					'<tr>';
								$html[] = 						'<td class="psp-icon-status">';
								
								$icon_status = 'is_success';
								if( $value_rule['ruleImpact'] < 3 && $value_rule['ruleImpact'] > 0 ){
									$icon_status = 'is_error';
								}elseif( $value_rule['ruleImpact'] < 20 && $value_rule['ruleImpact'] > 3 ){
									$icon_status = 'is_warning';
								}
								$html[] = 							'<i class="psp-status-icon ' . ( $icon_status ) . '"></i>';
								$html[] = 						'</td>';
								$html[] = 						'<td>';
								$html[] = 							'<a href="#" class="psp-criteria">' . ( $value_rule['localizedRuleName'] ) . '</a>';
								if( count($value_rule['urlBlocks']) > 0 ){
									
									$html[] = 					'<div class="psp-desc-complete">';
										$html[] = 						'<ul class="can-do">';
									foreach ($value_rule['urlBlocks'] as $key_blocks => $value_blocks) {
										
										$header_format = $value_blocks['header']['format'];
										$hyperlink = '';
					                    if(isset($value_blocks['header']['args'])) {
					                        $cc = 1;
					                        foreach($value_blocks['header']['args'] as $arg)
					                        {
					                            $header_format = str_replace('$' . $cc, $arg['value'], $header_format);
					                            if($arg['type'] == 'HYPERLINK') {
					                                $hyperlink = $arg['value'];
					                            }
												
					                            $cc++;
					                        }
					                    }
										
										if(isset($value_blocks['urls'])) {
											$html[] = '<ul>';
											foreach ($value_blocks['urls'] as $key_url => $value_url)
											{
												$link_format = $value_url['result']['format'];
												$cc = 1;	
												foreach($value_url['result']['args'] as $arg)
						                        {
						                        	$link_format = str_replace('$' . $cc, $arg['value'], $link_format);
													$html[] = '<li>' . ( $link_format ) . '</li>';
													
													$cc++;
												}
											}
											$html[] = '</ul>';
										}
										
					                    
										$html[] = '<li>';
										$html[] = 	'<p>' . $header_format . '<p>';
										if( trim($hyperlink) != "" ){
											$html[] = '<a href="' . ( $hyperlink ) . '" class="psp-button gray" target="_blank">' . ( __('Read Documentation', $this->the_plugin->localizationName) ) . '</a>';
										}
										$html[] = '</li>';
										
									}

										$html[] = 						'</ul>';
										$html[] = 					'</div>';
								}
								
								
								$html[] = 						'</td>';
								$html[] = 					'</tr>';
							}
						}
						
						$html[] = 						'</table>';
						$html[] = 					'</div>';
						$html[] = 				'</div>';
						$html[] = 			'</div>';
						$html[] = 		'</div>';
						
					  
						$html[] = '
						<div class="psp-grid_4">
							<div class="psp-panel">
								<div class="psp-panel-header">
									<div class="psp-panel-title">' . ( __('Understanding the Rule Icons', $this->the_plugin->localizationName) ) . '</div>
								</div>
								<div class="psp-panel-content psp-statistics-box">
									<div class="psp-sub-panel-content">
										<table class="psp-table">
											<tbody>
												<tr>
													<td class="psp-icon-status">
														<i class="psp-status-icon is_error"></i>
													</td>
													<td>' . ( __('red exclamation point', $this->the_plugin->localizationName) ) . '</td>
													<td>' . ( __('Fixing this would have a measurable impact on page performance.', $this->the_plugin->localizationName) ) . '</td>
												</tr>
												<tr>
													<td class="psp-icon-status">
														<i class="psp-status-icon is_warning"></i>
													</td>
													<td>' . ( __('yellow exclamation point', $this->the_plugin->localizationName) ) . '</td>
													<td>' . ( __('Consider fixing this if it is not an onerous amount of work.', $this->the_plugin->localizationName) ) . '</td>
												</tr>
												<tr>
													<td class="psp-icon-status">
														<i class="psp-status-icon is_success"></i>
													</td>
													<td>' . ( __('green check mark', $this->the_plugin->localizationName) ) . '</td>
													<td>' . ( __('No significant issues found. Good job!', $this->the_plugin->localizationName) ) . '</td>
												</tr>
											</tbody>
										</table>
									</div>
								</div>
							</div>
						</div>
						';
						
						$html[] = 	'</div>';
						
						$html[] = 	'<div class="right">';
						$html[] = 		'<div class="psp-grid_4">';
						$html[] = 			'<div class="' . ( $key_device ) . '-display">';
						$html[] = 				'<img src="' . ( $img ) . '" width="320">';
						$html[] = 				'<span class="php-the-mask"></span>';
						$html[] = 			'</div>';
						$html[] = 		'</div>';
						$html[] = 		'<div class="psp-grid_4">';
						$html[] = 			'<div class="psp-panel">';
						$html[] = 				'<div class="psp-panel-header">';
						$html[] = 					'<div class="psp-panel-title">';
						$html[] = 						__('Page Score:', $this->the_plugin->localizationName);
						
						$score = (int)$value_device['score'];
						$size_class = 'size_';
						if ( $score >= 20 && $score < 40 ){
							$size_class .= '20_40';
						}elseif ( $score >= 40 && $score < 60 ){
							$size_class .= '40_60';
						}
						elseif ( $score >= 60 && $score < 80 ){
							$size_class .= '60_80';
						}elseif ( $score >= 80 && $score <= 100 ){
							$size_class .= '80_100';
						}else{
							$size_class .= '0_20';
						}

						if( !isset($item_data['score']) || trim($item_data['score']) == "" ){
							$item_data['score'] = 0;
						}

						$html[] = 					'<div class="psp-progress">';
						$html[] = 						'<div style="width:' . ( $score ) . '%" class="psp-progress-bar ' . ( $size_class ) . '"></div>';
						$html[] = 						'<div class="psp-progress-score">' . ( $score ) . '/100</div>';
						$html[] = 					'</div>';
						$html[] = 				'</div>';
						$html[] = 			'</div>';
						$html[] = 		'</div>';
						$html[] = 	'</div>';
						$html[] = 	'<div class="psp-grid_4">';
						$html[] = 		'<div class="psp-panel">';
						$html[] = 			'<div class="psp-panel-header">';
						$html[] = 				'<div class="psp-panel-title">' . ( __('Page Statistics', $this->the_plugin->localizationName) ) . '</div>';
						$html[] = 			'</div>';
						$html[] = 		'<div class="psp-panel-content psp-statistics-box">';
						$html[] = 			'<div class="psp-sub-panel-content">';
						$html[] = 				'<table class="psp-table">';
						$html[] = 					'<tr>';
						$html[] = 						'<td>Last Checked</td>';
						$html[] = 						'<td>' . ( date("F j, Y, g:i a", $value_device['_create_at']) ) . '</td>';
						$html[] = 					'</tr>';
						$html[] = 					'<tr>';
						$html[] = 						'<td>Number of Hosts</td>';
						$html[] = 						'<td>' . ( $stats['numberHosts'] ) . '</td>';
						$html[] = 					'</tr>';
						$html[] = 					'<tr>';
						$html[] = 						'<td>Total Request Bytes</td>';
						$html[] = 						'<td>' . ( $this->formatBytes( $stats['totalRequestBytes'] ) ) . '</td>';
						$html[] = 					'</tr>';
						$html[] = 					'<tr>';
						$html[] = 						'<td>Total Resources</td>';
						$html[] = 						'<td>' . ( $stats['numberResources'] ) . '</td>';
						$html[] = 					'</tr>';
						$html[] = 					'<tr>';
						$html[] = 						'<td>JavaScript Resources</td>';
						$html[] = 						'<td>' . ( $stats['numberJsResources'] ) . '</td>';
						$html[] = 					'</tr>';
						$html[] = 					'<tr>';
						$html[] = 						'<td>CSS Resources</td>';
						$html[] = 						'<td>' . ( $stats['numberCssResources'] ) . '</td>';
						$html[] = 					'</tr>';
						$html[] = 				'</table>';
						$html[] = 			'</div>';
						$html[] = 		'</div>';
						$html[] = 	'</div>';
						$html[] = '</div>';
						
						$html[] = '<div class="psp-grid_4">';
						$html[] = 	'<div class="psp-panel">';
						$html[] = 		'<div class="psp-panel-header">';
						$html[] = 			'<div class="psp-panel-title">' . ( __('Page Resources Usages', $this->the_plugin->localizationName) ) . '</div>';
						$html[] = 		'</div>';
						$html[] = 		'<div class="psp-panel-content psp-resources-box">';
						$html[] = 			'<div class="psp-sub-panel-content">';
						$html[] = 				'<div id="psp-' . ( $key_device ) . '-graph" style="height: 200px;width: 100%; "></div>';
						$html[] = '
							<script>
								data = [];
								data[0] = {
									label: "HTML - ' . ( $this->formatBytes( $stats['htmlResponseBytes'] ) ) . '",
									data: ' . ( $stats['htmlResponseBytes'] ) . '
								};
								data[1] = {
									label: "CSS - ' . ( $this->formatBytes( $stats['cssResponseBytes'] ) ) . '",
									data: ' . ( $stats['cssResponseBytes'] ) . '
								};
								data[2] = {
									label: "Images - ' . ( $this->formatBytes( $stats['imageResponseBytes'] ) ) . '",
									data: ' . ( $stats['imageResponseBytes'] ) . '
								};
								data[3] = {
									label: "JavaScript - ' . ( $this->formatBytes( $stats['javascriptResponseBytes'] ) ) . '",
									data: ' . ( $stats['javascriptResponseBytes'] ) . '
								};
								data[4] = {
									label: "Others - ' . ( $this->formatBytes( $stats['otherResponseBytes'] ) ) . '",
									data: ' . ( $stats['otherResponseBytes'] ) . '
								};
								
								jQuery.plot(jQuery("#psp-' . ( $key_device ) . '-graph"), data,
								{
							        series: {
							            pie: {
							                show: true
							            }
							        }
								});
							</script>
						';
						$html[] = 			'</div>';
						$html[] = 		'</div>';
						$html[] = 	'</div>';
						$html[] = '</div>';
						
						$html[] = '</div>';
						$html[] = '</div>';
						
						$cc++;
					}
					$html[] = '</div>';
				}

				$return['viewSpeedRaportById'] = array(
					'status'	=> 'valid',
					'html' 		=> implode("\n", $html)
				);
			}
			
			die(json_encode($return));
		}
		
		private function check_page( $url='', $page_id=0 )
		{
			$settings = $this->the_plugin->getAllSettings( 'array', 'pagespeed' );
			$desktop_score = $mobile_score = 0;
			
			$google_api_url = sprintf( 'https://www.googleapis.com/pagespeedonline/v1/runPagespeed?url=%s&screenshot=true&snapshots=true&key=%s&locale=%s', urlencode($url), $settings['developer_key'], $settings['google_language'] );
			if( $settings['report_type'] == 'both' || $settings['report_type'] == 'desktop' ){
				
				// try to get from cache
				$response = get_post_meta( $page_id, 'psp_desktop_pagespeed', true );  
				
				if( $this->cache_lifetime > 0 && ( time() > ($response['_create_at'] + $this->cache_lifetime) ) ){
					// save the desktop results 
					$response = $this->getRemote( $google_api_url . '&strategy=desktop' );
	
					$this->saveTheImage( $page_id, 'desktop', $response['screenshot'] );
					if( isset($response['screenshot']) ){
						unset($response['screenshot']);
					}
				}
				
				// save add create at key and save the results to the DB
				$response['_create_at'] = time();
				update_post_meta( $page_id, 'psp_desktop_pagespeed', $response );
				$desktop_score = isset($response['score']) ?  $response['score'] : 0; 
			}
			
			if( $settings['report_type'] == 'both' || $settings['report_type'] == 'mobile' ){
				
				// try to get from cache
				$response = get_post_meta( $page_id, 'psp_mobile_pagespeed', true );  
				if( $this->cache_lifetime > 0 && ( time() > ($response['_create_at'] + $this->cache_lifetime) ) ){
					// save the mobile results 
					$response = $this->getRemote( $google_api_url . '&strategy=mobile' );
	
					$this->saveTheImage( $page_id, 'mobile', $response['screenshot'] );
					if( isset($response['screenshot']) ){
						unset($response['screenshot']);
					}
				}
				
				// save add create at key and save the results to the DB
				$response['_create_at'] = time();
				update_post_meta( $page_id, 'psp_mobile_pagespeed', $response ); 
				
				$mobile_score = isset($response['score']) ?  $response['score'] : 0;
			}
			
			return array(
				'desktop_score' => $desktop_score,
				'mobile_score' => $mobile_score
			);
		}
		
		private function saveTheImage( $post_id=0, $device='desktop', $the_image=array() )
		{
			$upload_dir = wp_upload_dir();
			if(! is_dir( $upload_dir['path'] . '' . $this->file_cache_directory )){
				@mkdir( $upload_dir['path'] . '' . $this->file_cache_directory );
				if(! is_dir( $upload_dir['path'] . '' . $this->file_cache_directory )){
					die("Could not create the file cache directory.");
					return false;
				}
			}

			// gogole replace the / with _ and + with - for keep the json response valid, so we need to roll back
			$the_image_data = str_replace("_", '/', $the_image['data']);
			$the_image_data = str_replace("-", '+', $the_image_data);
			
			file_put_contents( sprintf($upload_dir['path'] . '' . $this->file_cache_directory . '/%d-%s.jpg', $post_id, $device), @base64_decode($the_image_data));	
		}

		private function getRemote( $the_url )
		{ 
			$response = wp_remote_get( $the_url, array( 'timeout' => 30 ) );
			// If there's error
            if ( is_wp_error( $response ) ){
            	return array(
					'status' => 'invalid'
				);
            }
        	$body = wp_remote_retrieve_body( $response );
		
	        return json_decode( $body, true );
		}
		
		private function formatBytes($bytes=0)
		{
		    if ($bytes >= 1073741824)
	        {
	            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
	        }
	        elseif ($bytes >= 1048576)
	        {
	            $bytes = number_format($bytes / 1048576, 2) . ' MB';
	        }
	        elseif ($bytes >= 1024)
	        {
	            $bytes = number_format($bytes / 1024, 2) . ' KB';
	        }
	        elseif ($bytes > 1)
	        {
	            $bytes = $bytes . ' bytes';
	        }
	        elseif ($bytes == 1)
	        {
	            $bytes = $bytes . ' byte';
	        }
	        else
	        {
	            $bytes = '0 bytes';
	        }
	
	        return $bytes;
		}
    }
}