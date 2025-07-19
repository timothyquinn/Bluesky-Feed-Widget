<?php

	function getBlueskyFeed($account_handle, $app_password, $params = null) {

		// check for errors
			global $bluesky_error;

			if (!$account_handle || !$app_password) {
				$bluesky_error = "Missing mandatory parameters";
				return false;
			}

		// configure
			$config = [
				'account_handle' => $account_handle, // e.g. georgetakei.bsky.social
				'app_password' => $app_password, // obtain app password here: https://bsky.app/settings/app-passwords
				'target_username' => (@$params['target_username'] ? $params['target_username'] : $account_handle),
				'max_posts' => (@$params['max_posts'] ? $params['max_posts'] : 100), // must be integer from 1 to 100
				'date_time_format' => (@$params['date_time_format'] ? $params['date_time_format'] : 'M j, Y'),
				'display_stats' => (@$params['display_stats'] ? true : false),
				'filter' => (@$params['filter'] ? $params['filter'] : 'posts_no_replies'), // see Bluesky documentation for options
				'return_html' => (@$params['return_html'] ? true : false) // employs Bootstrap (4.x)
			];

			if ($config['return_html']) $output = null;
			else $output = [];

		// obtain session token
			$postdata = [
				'identifier' => $config['account_handle'],
				'password' => $config['app_password']
			];

			$curl = curl_init();
			curl_setopt_array(
				$curl,
				[
					CURLOPT_URL => 'https://bsky.social/xrpc/com.atproto.server.createSession',
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => '',
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 0,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => 'POST',
					CURLOPT_POSTFIELDS => json_encode($postdata),
					CURLOPT_HTTPHEADER => ['Content-Type: application/json']
				]
			);
			$response = curl_exec($curl);
			curl_close($curl);
			$session = json_decode($response, true);

		// query data
			if (is_array($session) && !empty($session) && array_key_exists('accessJwt', $session)) {

				$curl = curl_init();
				curl_setopt_array(
					$curl,
					[
						CURLOPT_URL => 'https://bsky.social/xrpc/app.bsky.feed.getAuthorFeed?actor=' . $config['target_username'] . '&limit=' . $config['max_posts'] . '&filter=' . $config['max_posts'],
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_ENCODING => '',
						CURLOPT_FOLLOWLOCATION => true,
						CURLOPT_MAXREDIRS => 10,
						CURLOPT_TIMEOUT => 0,
						CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
						CURLOPT_CUSTOMREQUEST => 'GET',
						CURLOPT_HTTPHEADER => [
							'Content-Type: application/json',
							'Authorization: Bearer ' . $session['accessJwt']
						]
					]
				);
				$response = curl_exec($curl);
				$data = json_decode($response, true);
				curl_close($curl);
	
			}
			else {
				$bluesky_error = "Unable to acquire session token";
				return false;
			}

		// parse data
			if(is_array($data) && !empty($data) && array_key_exists('feed', $data)) {

				$increment = 0;

				foreach ( $data['feed'] as $item ) {
					if ( $config['filter'] != 'posts_no_replies' || !array_key_exists( 'reply', $item ) ) {
						$increment++;
						$output[] = $item;
					}

					if ( $increment == $config['max_posts'] ) {
						break;
					}
				}

			}
			else {
				$bluesky_error = "No data returned from server" . ($data ? ": " . $data : null);
				return false;
			}

			if (!count($output)) {
				// no posts
					if ($config['return_html']) return null;
					else return [];
			}

		// return
			if ($config['return_html']) {

				echo "<div id='bsky-feed'>\n";

				$increment = 0;

				foreach ($output as $item) {

					$increment++;

					echo "  <div class='bsky-item'>\n";

					// metadata
						echo "    <div class='bsky-item-metadata'>\n";
						echo "      <div class='row align-items-center form-group'>\n";
						/* avatar */		echo "        <div class='col-lg-2 col-md-2 col-sm-3 col-3'><img src='" . $item['post']['author']['avatar'] . "' alt='" . htmlentities($item['post']['author']['displayName']) . "' class='img-fluid rounded-circle' /></div>\n";
						/* username */		echo "        <div class='col-lg-5 col-md-5 col-sm-5 col-5'><a href='https://bsky.app/profile/" . $item['post']['author']['handle'] . "' target='_blank'>" . htmlentities($item['post']['author']['displayName']) . "</a></div>\n";
						/* link */			$link_parts = explode('app.bsky.feed.post/', $item['post']['uri']);
											echo "        <div class='col-lg-5 col-md-5 col-sm-4 col-4 text-right'><small><a href='https://bsky.app/profile/" . $item['post']['author']['handle'] . "/post/" . $link_parts[1] . "' target='_blank' class='text-secondary'>" . date($config['date_time_format'], strtotime($item['post']['record']['createdAt'])) . "</a></small></div>\n";
						echo "      </div>\n";
						echo "    </div><!--bsky-item-metadata -->\n";

					// content
						echo "    <div class='bsky-item-content'>\n";
	
					// text
						echo "      <p>";

							// activate links
								if (array_key_exists('record', $item['post']) && array_key_exists('facets', $item['post']['record'])) {

									$replace = array();
									foreach ($item['post']['record']['facets'] as $link) {
										if (array_key_exists('features', $link) && is_array($link['features']) && !empty($link['features'])) {
											if($link['features'][0]['$type'] == 'app.bsky.richtext.facet#tag') {
												// hashtag
													$uri = "https://bsky.app/hashtag/" . $link['features'][0]['tag'];
													$length = $link['index']['byteEnd'] - $link['index']['byteStart'];
													$replace_this = substr($item['post']['record']['text'], $link['index']['byteStart'], $length);
													$replace[$replace_this] = "<a href='" . $uri . "' target='_blank'>#" . $link['features'][0]['tag'] . "</a>";
											}
											elseif($link['features'][0]['$type'] == 'app.bsky.richtext.facet#mention') {
												// hashtag
													$uri = "https://bsky.app/profile/" . $link['features'][0]['did'];
													$length = $link['index']['byteEnd'] - $link['index']['byteStart'];
													$replace_this = substr($item['post']['record']['text'], $link['index']['byteStart'], $length);
													$replace[$replace_this] = "<a href='" . $uri . "' target='_blank'>" . $replace_this . "</a>";
											}
											elseif($link['features'][0]['$type'] == 'app.bsky.richtext.facet#link') {
												// url
													$uri = $link['features'][0]['uri'];
													$length = $link['index']['byteEnd'] - $link['index']['byteStart'];
													$replace_this = substr($item['post']['record']['text'], $link['index']['byteStart'], $length);
													$replace[$replace_this] = "<a href='" . $uri . "' target='_blank'>" . $replace_this . "</a>";
											}
										}
									}

									echo "        " . nl2br(str_replace(array_keys($replace), $replace, $item['post']['record']['text']), false);

								}
								else {
									echo "        " . nl2br($item['post']['record']['text'], false);
								}

						echo "      </p>\n";

					// embeds
						if (array_key_exists('embed', $item['post'])) {

							// quoted post
								if(array_key_exists('$type', $item['post']['embed']) && $item['post']['embed']['$type'] == 'app.bsky.embed.recordWithMedia#view') {
									// both images and quoted post
								}
								elseif(array_key_exists('record', $item['post']['embed'])) {

									// just quoted post
										if($item['post']['embed']['record']['$type'] == 'app.bsky.embed.record#viewRecord') {

											echo "      <div class='bsky-embeds-record'><blockquote>\n";
											echo "        <div class='row align-items-center form-group'>\n";
											/* avatar */		echo "          <div class='col-lg-2 col-md-2 col-sm-3 col-3'><img src='" . $item['post']['embed']['record']['author']['avatar'] . "' alt='" . htmlentities($item['post']['embed']['record']['author']['displayName']) . "' class='img-fluid rounded-circle' /></div>\n";
											/* username */		echo "          <div class='col-lg-5 col-md-5 col-sm-5 col-5'><a href='https://bsky.app/profile/" . $item['post']['embed']['record']['author']['handle'] . "' target='_blank'>" . htmlentities($item['post']['embed']['record']['author']['displayName']) . "</a></div>\n";
											/* link */			$link_parts = explode('app.bsky.feed.post/', $item['post']['embed']['record']['uri']);
																echo "          <div class='col-lg-5 col-md-5 col-sm-4 col-4 text-right'><small><a href='https://bsky.app/profile/" . $item['post']['embed']['record']['author']['handle'] . "/post/" . $link_parts[1] . "' target='_blank' class='text-secondary'>" . date($config['date_time_format'], strtotime($item['post']['embed']['record']['value']['createdAt'])) . "</a></small></div>\n";
											echo "        </div>\n";

											echo "        <div class='bsky-item-embed-text'><p>";
											/* content */	echo nl2br($item['post']['embed']['record']['value']['text'], false);
											echo "        </p></div><!--bsky-item-embed-text -->\n";
											echo "      </blockquote></div><!-- bsky-embeds-record -->\n";

										}

								}

							// external link
								if (array_key_exists('external', $item['post']['embed'])) {

									if ($item['post']['embed']['$type'] == 'app.bsky.embed.external#view') {

										echo "      <div class='bsky-embeds-external'><blockquote>";
										echo "        <img src='" . $item['post']['embed']['external']['thumb'] . "' alt='' width='40' hspace='10' align='left' />\n";
										echo "        <a href='" . $item['post']['embed']['external']['uri'] . "' target='_blank'>\n";
										echo "        <strong>" . htmlentities($item['post']['embed']['external']['title']) . "</strong><br />\n";
										echo "        " . htmlentities($item['post']['embed']['external']['description']) . "\n";
										echo "        </a>\n";
										echo "      </blockquote></div><!-- bsky-embeds-external -->\n";

									}

								}

							// images
								if (array_key_exists('images', $item['post']['embed'])) {

									echo "      <div class='bsky-embeds-images'>\n";
									foreach ($item['post']['embed']['images'] as $bsky_image) {
										echo "        <p class='text-center'><a href='" . $bsky_image['fullsize'] . "' target='_blank'><img src='" . $bsky_image['thumb'] . "' alt='" . htmlentities($bsky_image['alt'], ENT_QUOTES) . "' class='img-fluid rounded' /></a></p>\n";
									}
									echo "      </div><!-- bsky-embeds-images -->\n";

								}

						}

					// end content
						echo "    </div><!--bsky-item-content -->\n";

					// stats
						if ($config['display_stats']) {
							echo "<div class='row bsky-item-stats'>\n";
							echo "  <div class='col-lg-4 col-md-4 col-sm-4 col-4'><small><b>Likes</b> " . number_format($item['post']['likeCount']) . "</small></div>\n";
							echo "  <div class='col-lg-4 col-md-4 col-sm-4 col-4 text-center'><small><b>Reposts</b> " . number_format($item['post']['repostCount']) . "</small></div>\n";
							echo "  <div class='col-lg-4 col-md-4 col-sm-4 col-4 text-right'><small><b>Replies</b> " . number_format($item['post']['replyCount']) . "</small></div>\n";
							echo "</div> <!-- bsky-item-stats -->\n";
						}

					echo "  </div><!-- bsky-item -->\n";

					echo "  <hr />\n";

				}

				echo "  <p><a href='https://bsky.app/profile/" . $config['account_handle'] . "' target='_blank'>See more posts on Bluesky</a></p>\n";

				echo "</div><!-- bsky-feed -->\n";

			}
			else {
				return $output;
			}

	}

?>