<?php
/**
* クローラークラス
*
*/
class Crawler {
	function exec($prefcd) {
		global $crawl_url_base, $crawl_url_search_pref;

		// ------------------------------
		// 検索対象サイト情報取得
		// ------------------------------
		$base_url = str_replace("{prefcd}", $prefcd, $crawl_url_base);
		$url_search_pref = $base_url.str_replace("{prefcd}", $prefcd, $crawl_url_search_pref);

		// ------------------------------
		// 変数初期化
		// ------------------------------
		$businesses = array();
		$results = array();
		$results["total_count"] = 0;
		$results["business_ids"] = array();
		$results["detail_urls"] = array();

		// ------------------------------
		// 事業所一覧　クロール
		// ------------------------------
		Util::putlog("[list crawl]start");
		// １ページ目
		$this->get_search_result($prefcd, $url_search_pref, $results);
		$total_count = $results["total_count"];
		if (!$total_count) {
			Util::putlog("(failed) get total_count", "error");
			return;
		}

		// デバッグメッセージ
		Util::putlog("total_count=".$total_count);

		$page_row_count = 5;
		$page_count = floor($total_count/$page_row_count)+((($total_count%$page_row_count)==0) ? 0 : 1);
		
		//TODO Remove this
		//To test results
		//$page_count = 2;
		
		// ２ページ目以降
		for ($i=1; $i< $page_count; $i++) {
			$page_offset = $page_row_count * $i;
			if (!$this->get_search_result($prefcd, $url_search_pref, $results, $page_offset)) {
				Util::putlog("(failed) list crawl | page_offset=".$page_offset, "error");
				break;
			}

			// デバッグメッセージ（500件毎に出力）
			if (($i % 100)==0) {
				Util::putlog("page_offset=".$page_offset);
			}
		}

		// 一覧から取得したデータを元に、事業所ID、詳細URL、サービスコードを取得
		foreach ($results["business_ids"] as $key=>$value) {
			$business = array();
			$business["business_id"] = $value;
			$business["detail_url"] = str_replace("&amp;", "&", $results["detail_urls"][$key]);
			$business["servicecd"] = substr($business["detail_url"], -3);
			$businesses[] = $business;
		}
		Util::putlog("[list crawl]end");
		// ------------------------------
		// 事業所詳細　クロール
		// ------------------------------
		Util::putlog("[detail crawl]start");
		foreach ($businesses as $key=>$value) {
			// 一覧から取得した情報を変数に格納
			$business = array();
			$business = $value;

			// HTML読み込み
			$url = $base_url.$business["detail_url"];
			$html_contents = file_get_contents($url);
			if ($html_contents) {
				// 「事業所の概要」コンテンツ読み込み
				$this->set_contents_group1($html_contents, $business);
				$this->set_contents_chart($html_contents, $business);
				$this->set_contents_group2($html_contents, $business);
				$this->set_contents_group4($html_contents, $business);
				$this->set_contents_group5($html_contents, $business);
				$this->set_contents_employee($html_contents, $business);
				$this->set_contents_userinfo($html_contents, $business);
				$this->set_contents_others($html_contents, $business);
				$this->set_contents_business_specialty($html_contents, $business);
				$this->set_contents_from_diffid($html_contents, $business);

				// 「事業所の詳細」コンテンツ読み込み
				$url = str_replace("_kani", "_kihon", $url);
				$html_contents = file_get_contents($url);
				if ($html_contents) {
					$this->set_contents_shozaichi($html_contents, $business);
					$this->set_contents_from_diffid($html_contents, $business);

					// データ更新
					$this->update_db($business);
				} else {
					Util::putlog("detal_kihon no data.url=".$url, "error");
				}
			} else {
				Util::putlog("detal no data.url=".$url, "error");
			}
		}
		Util::putlog("[detail crawl]end");
	}

	// データ更新
	function update_db($business) {
		$servicecd = $business["servicecd"];

		$db = new DbAccess();
		$db->dbconnect();

		$mappings = $this->get_mappings($db, $servicecd);
		if ($mappings) {
			$this->update_kaigokensaku_business($db, $business, $mappings);
			$this->update_kaigokensaku_business_office($db, $business, $mappings);
			$this->update_kaigokensaku_business_staff($db, $business, $mappings);
			$this->update_kaigokensaku_business_service($db, $business, $mappings);
			$this->update_kaigokensaku_business_price($db, $business, $mappings);
			$this->update_kaigokensaku_business_other($db, $business, $mappings);
		} else {
			Util::putlog("no mapping data. servicecd=".$servicecd." business_id=".$business["business_id"], "warning");
		}

		$db->dbclose();
	}

	//  検索結果より、事業所ID、詳細画面URLを取得
	function get_search_result($prefcd, $url, &$results, $page_offset=0) {
		// コンテンツ読み込み
		if ($page_offset>0) {
		// ページング処理時
			$post_datas = array(
				'method'		=> 'pager',
				'action_kouhyou_pref_search_list_result'	=> 'true',
				'p_count'    => '5',
				'p_offset'		=> $page_offset,
				'p_sort_name'	=> '',
				'p_order_name'	=> '',
				'PrefCd'		=> $prefcd
			);
			$datas = http_build_query($post_datas, '', '&');
			$context = stream_context_create(array(
				'http' => array(
					'method'  => 'POST',
					'header'  => implode("\r\n", array(
									'Content-Type: application/x-www-form-urlencoded',
									'Content-Length: ' . strlen($datas)
								)),
					'content' => $datas
				)
			));
			$html_contents = file_get_contents($url, false, $context);
		} else {
		// 初期検索時
			$post_datas = array(
				'method'		=> 'result',
				'action_kouhyou_pref_search_list_list'	=> 'true',
				'p_count'    => '5',
				'p_offset'		=> '0',
				'p_sort'	=> '0',
				'p_order'	=> '0',
				'PrefCd'		=> $prefcd
			);
			$datas = http_build_query($post_datas, '', '&');
			$context = stream_context_create(array(
				'http' => array(
					'method'  => 'POST',
					'header'  => implode("\r\n", array(
									'Content-Type: application/x-www-form-urlencoded',
									'Content-Length: ' . strlen($datas)
								)),
					'content' => $datas
				)
			));
			$html_contents = file_get_contents($url, false, $context);
			// 検索件数取得
			$html_total = $this->get_html_contents($html_contents, "<div class=\"total\">", "</div>");
			if (preg_match('|<span class="redB">(.+)件</span>|', $html_total, $total)) {
				$results["total_count"] = $total[1];
			}
		}
		if (!$html_contents) {
			return false;
		}

		// 検索結果部分取得
		$html_search_results = $this->get_html_contents($html_contents, "<table id=\"searchResult\">", "</table>");

		// 事業所ID取得
		if (preg_match_all('|<input type="checkbox"\s[^>]*value="([^"/]+)|s', $html_search_results, $wb_ids)) {
			$results["business_ids"] = array_merge($results["business_ids"], $wb_ids[1]);
		}
		// 詳細URL取得
		if (preg_match_all('|<input type="button"\s[^>]*value="詳細" onclick=".+?\'([^\'"/]+)|s', $html_search_results, $detail_urls)) {
			$results["detail_urls"] = array_merge($results["detail_urls"], $detail_urls[1]);
		}

		return true;
	}

	// diffidを元にコンテンツ取得
	function set_contents_from_diffid($html_contents, &$results) {
		if (preg_match_all("/diffid='diff-c(.+?)'>/s", $html_contents, $preg_result)) {
//print_r($preg_result);
			foreach ($preg_result[1] as $id) {
				$start_tag = "diffid='diff-c".$id."'>";
				$end_tag = "</td>";
				$diff_value = "";
				$pos_start = strpos($html_contents, $start_tag)+strlen($start_tag);
				if ($pos_start!==FALSE) {
					$pos_end = strpos($html_contents, $end_tag, $pos_start);
					$diff_value = substr($html_contents, $pos_start, ($pos_end-$pos_start));
				}

				if ($diff_value=="&nbsp;"){
					$diff_value = "";
				} else {
					$ico_jigyosho = $this->gettext_ico_jigyosho($diff_value);
					if ($ico_jigyosho==="") {
						$diff_value = str_replace("\n", "", $diff_value);
					} else {
						$diff_value = $ico_jigyosho;
					}
				}
				$results["diff-c".$id] = str_replace(array("<br>", "<br />"), "\n", $diff_value);
			}
		}
	}

	// tableGroup-1の情報を取得
	function set_contents_group1($html_contents, &$results) {
		$html_group = $this->get_html_contents($html_contents, "<div class='block clearfix' id='tableGroup-1'>", "</table>");

		/* diffidからの取得に変更
		// 法人種別
		if (preg_match("|diffid='diff-c1'>(.+)</td>|", $html_group, $preg_result)) {
			$results["business_type"] = $preg_result[1];
		}
		// 事業所名
		if (preg_match("|diffid='diff-c175'>(.+)</td>|", $html_group, $preg_result)) {
			$results["business_name"] = $preg_result[1];
		}
		*/

		// 介護サービス
		$html_section = $this->get_html_contents($html_group, "<th  abbr='介護サービス'", "</td>");
		if (preg_match("/<td.*?>(.*?)<\/td>/ius", $html_section, $preg_result)) {
			$results["service_name"] = str_replace("\n", "", $preg_result[1]);
		}
		// 住所
		$html_section = $this->get_html_contents($html_group, "<th  abbr='住所'", "</td>");
		if (preg_match("/<td.*?>(.+?)<br \/>(.+?)<br \/>(.+?)<\/td>/s", $html_section, $preg_result)) {
			$results["business_zip"] = str_replace("\n", "", $preg_result[1]);
			$results["business_address"] = str_replace("\n", "", $preg_result[2]);
		}
		// 連絡先
		$html_section = $this->get_html_contents($html_group, "<th  abbr='連絡先'", "</td>");
		if (preg_match("/<td.*?>(.*?)<br \/>(.+?)<br \/>(.+)<\/td>/s", $html_section, $preg_result)) {
			$results["business_tel"] = str_replace("\n", "", $preg_result[1]);
			$results["business_fax"] = str_replace("\n", "", $preg_result[2]);
			if (preg_match("/href=\"(.*)\"/s", $preg_result[3], $preg_result2)) {
				$results["business_website"] = str_replace("\n", "", $preg_result2[1]);
			}
		}
		// 記入日
		$html_section = $this->get_html_contents($html_group, "<th  abbr='記入日'", "</td>");
		if (preg_match("/<td.*?>(.+?)<\/td>/s", $html_section, $preg_result)) {
			$results["written_date"] = str_replace("\n", "", $preg_result[1]);
		}
		//介護予防サービスの実施
		$html_section = $this->get_html_contents($html_group, "<th  abbr='介護予防サービスの実施'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["kaigoyobou"] = $this->gettext_ico_jigyosho($preg_result[1]);
		}
		//併設している介護サービス
		$html_section = $this->get_html_contents($html_group, "<th  abbr='併設している介護サービス'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["heisetsu_kaigo"] = $this->gettext_ico_jigyosho($preg_result[1]);
		}
	}

	// チャート取得
	function set_contents_chart($html_contents, &$results) {
		$html_chart = $this->get_html_contents($html_contents, "var targetChartVal = [", "]");
		$value_chart = str_replace("var targetChartVal = [", "", str_replace("]", "", $html_chart));
		$array_chart = explode(",", $value_chart);
		foreach (range(1, 7) as $i) {
			if (isset($array_chart[$i]))
				$results["chart$i"] = $array_chart[$i];
		
		}
	}

	// tableGroup-2の情報を取得
	function set_contents_group2($html_contents, &$results) {
		$html_group = $this->get_html_contents($html_contents, "<div class='block clearfix' id='tableGroup-2'>", "</table>");

		//延長サービスの有無
		$html_section = $this->get_html_contents($html_group, "<th  abbr='延長サービスの有無'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["service_extratime"] = $this->gettext_ico_jigyosho($preg_result[1]);
		}
	}

	// tableGroup-4の情報を取得
	function set_contents_group4($html_contents, &$results) {
		$html_group = $this->get_html_contents($html_contents, "<div class='block clearfix' id='tableGroup-4'>", "</table>");

		// 浴室設備の数
		$html_section = $this->get_html_contents($html_group, "<th  abbr='浴室設備の数'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["utility_bath"] = str_replace("\n", "", $preg_result[1]);
		}
		// 個室
		$html_section = $this->get_html_contents($html_group, "<th  abbr='個室'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["utility_room1"] = str_replace("\n", "", $preg_result[1]);
		}
		// 2人部屋
		$html_section = $this->get_html_contents($html_group, "<th  abbr='2人部屋'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["utility_room2"] = str_replace("\n", "", $preg_result[1]);
		}
		// 3人部屋
		$html_section = $this->get_html_contents($html_group, "<th  abbr='3人部屋'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["utility_room3"] = str_replace("\n", "", $preg_result[1]);
		}
		// 4人部屋
		$html_section = $this->get_html_contents($html_group, "<th  abbr='4人部屋'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["utility_room4"] = str_replace("\n", "", $preg_result[1]);
		}
		// 5人部屋以上
		$html_section = $this->get_html_contents($html_group, "<th  abbr='5人部屋以上'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["utility_room5"] = str_replace("\n", "", $preg_result[1]);
		}
		// 病床数（介護保険）
		$html_section = $this->get_html_contents($html_group, "<th  abbr='病床数（介護保険）'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["utility_bed"] = str_replace("\n", "", $preg_result[1]);
		}
	}

	// tableGroup-5の情報を取得
	function set_contents_group5($html_contents, &$results) {
		$html_group = $this->get_html_contents($html_contents, "<div class='block clearfix' id='tableGroup-5'>", "</table>");

		// 家賃（月額）
		$html_section = $this->get_html_contents($html_group, "<th  abbr='家賃（月額）'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["fee_rent"] = str_replace("\n", "", $preg_result[1]);
		}
		// 敷金
		$html_section = $this->get_html_contents($html_group, "<th  abbr='敷金'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["fee_deposit1"] = str_replace("\n", "", $preg_result[1]);
		}
		// 保証金（入居時一時金）の金額
		$html_section = $this->get_html_contents($html_group, "<th  abbr='保証金（入居時一時金）の金額'", "</td>");
		if (preg_match("/src=\"(.*)\"/s", $html_section, $preg_result)) {
			$results["fee_deposit2"] = str_replace("\n", "", $preg_result[1]);
		}
	}

	function set_contents_employee($html_contents, &$results) {
		// ------------------------------
		// 従業員情報
		// ------------------------------
		$html_group = $this->get_html_contents($html_contents, "<div class='block clearfix' id='tableGroup-5'>", "</table>");
		// 総従業員数
		$html_section = $this->get_html_contents($html_group, "<th class='border_bottom_none' abbr='総従業員数'", "</tr>");
		if (preg_match("/<td.*?>(.+?)<\/td>/s", $html_section, $preg_result)) {
			$results["employee_total"] = str_replace("\n", "", $preg_result[1]);
		}
		// ケアマネジャー数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"ケアマネジャー数", 
				"employee_caremanager"
			);
		// うち主任ケアマネジャー数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"うち主任ケアマネジャー数", 
				"employee_caremanager_head"
			);
		// ケアマネジャーの退職者数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"ケアマネジャーの退職者数", 
				"employee_caremanager_retire"
			);
		// ケアマネジャーのうち看護師の資格を持つ従業員数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"ケアマネジャーのうち看護師の資格を持つ従業員数", 
				"employee_caremanager_kangoshi"
			);
		// ケアマネジャーのうち介護福祉士の資格を持つ従業員数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"ケアマネジャーのうち介護福祉士の資格を持つ従業員数", 
				"employee_caremanager_kaigohukushi"
			);
		// 経験年数5年以上のケアマネジャーの割合
		$this->setvalue_employee_rate(
				$html_group, 
				$results,
				"経験年数5年以上のケアマネジャーの割合", 
				"employee_caremanager_over5_rate"
			);
		// 訪問介護員等数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"訪問介護員等数", 
				"employee_kaigoin"
			);
		// 訪問介護員等の退職者数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"訪問介護員等の退職者数", 
				"employee_kaigoin_retire"
			);
		// 訪問介護員等のうち介護福祉士の資格を持つ従業員数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"訪問介護員等のうち介護福祉士の資格を持つ従業員数",
				"employee_kaigoin_kaigohukushi"
			);
		// 経験年数5年以上の介護職員の割合
		$this->setvalue_employee_rate(
				$html_group, 
				$results,
				"経験年数5年以上の介護職員の割合", 
				"employee_kaigoin_over5_rate"
			);
		// 看護師・准看護師数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"看護師・准看護師数", 
				"employee_kngoshi"
			);
		// 看護師・准看護師の退職者数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"看護師・准看護師の退職者数", 
				"employee_kngoshi_retire"
			);
		// 介護職員数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"介護職員数", 
				"employee_shokuin"
			);
		// 介護職員の退職者数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"介護職員の退職者数", 
				"employee_shokuin_retire"
			);
		// 介護職員のうち介護福祉士の資格を持つ従業員数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"介護職員のうち介護福祉士の資格を持つ従業員数", 
				"employee_shikaku_fukushi"
			);
		// 経験年数5年以上の従業員の割合
		$this->setvalue_employee_rate(
				$html_group, 
				$results,
				"経験年数5年以上の従業員の割合", 
				"employee_over5_rate"
			);
		// 保健師数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"保健師数", 
				"employee_hoken"
			);
		// 保健師・看護師・准看護師の退職者数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"保健師・看護師・准看護師の退職者数", 
				"employee_hoken_retire"
			);
		// 経験年数5年以上の保健師・看護師・准看護師の割合
		$this->setvalue_employee_rate(
				$html_group, 
				$results,
				"経験年数5年以上の保健師・看護師・准看護師の割合", 
				"employee_hoken_over5_rate"
			);
		// 理学療法士・作業療法士・言語聴覚士の数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"理学療法士・作業療法士・言語聴覚士の数", 
				"employee_rigaku"
			);
		// 理学療法士・作業療法士・言語聴覚士の退職者数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"理学療法士・作業療法士・言語聴覚士の退職者数", 
				"employee_rigaku_retire"
			);
		// 経験年数5年以上の理学療法士・作業療法士・言語聴覚士の割合
		$this->setvalue_employee_rate(
				$html_group, 
				$results,
				"経験年数5年以上の理学療法士・作業療法士・言語聴覚士の割合", 
				"employee_rigaku_over5_rate"
			);
		// オペレーター数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"オペレーター数", 
				"employee_operator"
			);
		// オペレーターの退職者数　
		$this->setvalue_employee(
				$html_group, 
				$results,
				"オペレーター数", 
				"employee_operator_retire"
			);
		// 看護職員数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"看護職員数", 
				"employee_kango"
			);
		// 看護職員の退職者数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"看護職員の退職者数", 
				"employee_kango_retire"
			);
		// 経験年数5年以上の看護職員・介護職員の割合
		$this->setvalue_employee_rate(
				$html_group, 
				$results,
				"経験年数5年以上の理学療法士・作業療法士・言語聴覚士の割合", 
				"employee_kango_over5_rate"
			);
		// 従業者の退職者数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"従業者の退職者数", 
				"employee_retire"
			);
		// 夜勤を行う従業員数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"夜勤を行う従業員数", 
				"employee_nightwork"
			);
		// 計画作成担当者数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"計画作成担当者数", 
				"employee_create_plan"
			);
		// 看護師数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"看護師数", 
				"employee_kangoshi"
			);
		// 福祉用具専門相談員数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"福祉用具専門相談員数", 
				"employee_yougu"
			);
		// 福祉用具専門相談員の退職者数
		$this->setvalue_employee(
				$html_group, 
				$results,
				"福祉用具専門相談員の退職者数", 
				"employee_yougu_retire"
			);
		// 経験年数5年以上の福祉用具専門相談員の割合
		$this->setvalue_employee_rate(
				$html_group, 
				$results,
				"経験年数5年以上の福祉用具専門相談員の割合", 
				"employee_yougu_over5_rate"
			);
	}

	// 従業員　常勤／非常勤
	function setvalue_employee($html, &$results, $start_value, $id) {
		$start_tag = "<th  abbr='".$start_value."'";
		$end_tag = "</tr>";
		$html_section = $this->get_html_contents($html, $start_tag, $end_tag);
		if ($html_section=="") {
			return;
		}
		if (preg_match_all("/<td.*?>(.+?)<\/td>/s", $html_section, $preg_result)) {
//			$results[$id."_fulltime"] = $preg_result[1][0];
//			$results[$id."_parttime"] = $preg_result[1][1];
			if (isset($preg_result[1])) {
				$results[$id] = str_replace("\n", "", $preg_result[1][0].",".$preg_result[1][1]);
			}
		}
	}

	// 従業員　割合
	function setvalue_employee_rate($html, &$results, $start_value, $id) {
		$start_tag = "<th  abbr='".$start_value."'";
		$end_tag = "</tr>";
		$html_section = $this->get_html_contents($html, $start_tag, $end_tag);
		if ($html_section=="") {
			return;
		}
		if (preg_match("/<td.*?>(.+?)<\/td>/s", $html_section, $preg_result)) {
			$results[$id] = str_replace("\n", "", $preg_result[1]);
		}
	}

	// 概要 - 利用者情報
	function set_contents_userinfo($html_contents, &$results) {
		// 該当箇所のhtml取得
		$html_group = $this->get_html_contents($html_contents, "<div class='block clearfix' id='tableGroup-6'>", "</table>");

		// 利用者総数(都道府県平均もあり）
		$this->setvalue4abbr($html_group, $results, "利用者総数", "userinfo_total");
	//	if ($results["userinfo_total"]) {
	//		$values = explode("＜", str_replace(array("\n", "＞"), "", $results["userinfo_total"]));
	//		$results["userinfo_total"] = $values[0];
	//		$results["userinfo_total_avg"] = $values[1];
	//	}
		// 登録者の平均年齢
		$this->setvalue4abbr($html_group, $results, "登録者の平均年齢", "userinfo_age_avg");
		// 登録者の男女別人数 - 男性
		$this->setvalue4abbr($html_group, $results, "男性", "userinfo_male");
		// 登録者の男女別人数 - 女性
		$this->setvalue4abbr($html_group, $results, "女性", "userinfo_female");
		// 要介護度別入所者数 - 自立
		$this->setvalue4abbr($html_group, $results, "自立", "userinfo_self");
		// 要介護度別入所者数 - 要支援１
		$this->setvalue4abbr($html_group, $results, "要支援１", "userinfo_shien1");
		// 要介護度別入所者数 - 要支援２
		$this->setvalue4abbr($html_group, $results, "要支援２", "userinfo_shien2");
		// 要介護度別入所者数 - 要介護１
		$this->setvalue4abbr($html_group, $results, "要介護１", "userinfo_kaigo1");
		// 要介護度別入所者数 - 要介護２
		$this->setvalue4abbr($html_group, $results, "要介護２", "userinfo_kaigo2");
		// 要介護度別入所者数 - 要介護３
		$this->setvalue4abbr($html_group, $results, "要介護３", "userinfo_kaigo3");
		// 要介護度別入所者数 - 要介護４
		$this->setvalue4abbr($html_group, $results, "要介護４", "userinfo_kaigo4");
		// 要介護度別入所者数 - 要介護５
		$this->setvalue4abbr($html_group, $results, "要介護５", "userinfo_kaigo5");
		// 利用者の平均的な利用日数
		$this->setvalue4abbr($html_group, $results, "利用者の平均的な利用日数", "userinfo_use_avg");
		// 昨年度の退所者数
		$this->setvalue4abbr($html_group, $results, "昨年度の退所者数", "userinfo_retire_lastyear");
		// ３か月間の退所者数
		$this->setvalue4abbr($html_group, $results, "昨年度の退所者数", "userinfo_retire_3month");
		// 入所者の平均的な入所日数
		$this->setvalue4abbr($html_group, $results, "昨年度の退所者数", "userinfo_stay_avg");
		// 待機者数
		$this->setvalue4abbr($html_group, $results, "待機者数", "userinfo_wait");
		// 入所率
		$this->setvalue4abbr($html_group, $results, "入所率", "userinfo_stay_rate");
	}

	// 概要 - その他
	function set_contents_others($html_contents, &$results) {
		// 該当箇所のhtml取得
		$html_group = $this->get_html_contents($html_contents, "<div class='block clearfix' id='tableGroup-7'>", "</table>");

		// 苦情相談窓口
		//$this->setvalue4abbr($html_group, $results, "苦情相談窓口", "others_contact");
		// 利用者の意見を把握する取組
		//$this->setvalue_ico_jigyosho($html_group, $results, "利用者の意見を把握する取組", "others_userissue");
		// 第三者による評価
		$this->setvalue_ico_jigyosho($html_group, $results, "第三者による評価", "others_thirdparty");
		// 損害賠償保険の加入
		//$this->setvalue_ico_jigyosho($html_group, $results, "損害賠償保険の加入", "others_nonlife_insurance");
		// 法人等が実施するサービス
		$this->setvalue4abbr($html_group, $results, "法人等が実施するサービス", "others_business_service");
	}

	// 概要 - 事業所の特色
	function set_contents_business_specialty($html_contents, &$results) {

		// 従業員の男女比 - 女 / 男
		$html_section = $this->get_html_contents($html_contents, "<h3><span>●</span>従業員の男女比</h3>", "</table>");
		$sexlist = array(
						array("id"=>"female", "word"=>"女"),
						array("id"=>"male", "word"=>"男")
					);
		foreach($sexlist as $item) {
			$html_value = $this->get_html_contents($html_section, "<th>".$item["word"]."</th>", "</tr>");
			if (preg_match("/<td.*?>(.+?)<\/td>/s", $html_value, $preg_result)) {
				$results["business_specialty_employee_".$item["id"]."_rate"] = str_replace(array("\n", "&nbsp;"), "", $preg_result[1]);
			}
		}
		// 利用者の男女比 - 女 / 男
		$html_section = $this->get_html_contents($html_contents, "<h3><span>●</span>利用者の男女比</h3>", "</table>");
		$sexlist = array(
						array("id"=>"female", "word"=>"女"),
						array("id"=>"male", "word"=>"男")
					);
		foreach($sexlist as $item) {
			$html_value = $this->get_html_contents($html_section, "<th>".$item["word"]."</th>", "</tr>");
			if (preg_match("/<td.*?>(.+?)<\/td>/s", $html_value, $preg_result)) {
				$results["business_specialty_user_".$item["id"]."_rate"] = str_replace(array("\n", "&nbsp;"), "", $preg_result[1]);
			}
		}
		// 従業員の年齢構成 - 20代 / 30代 / 40代 / 50代 / 60代以上
		$html_section = $this->get_html_contents($html_contents, "<h3><span>●</span>従業員の年齢構成</h3>", "</table>");
		$agelist = array(
						array("id"=>"20", "word"=>"20代"),
						array("id"=>"30", "word"=>"30代"),
						array("id"=>"40", "word"=>"40代"),
						array("id"=>"50", "word"=>"50代"),
						array("id"=>"over60", "word"=>"60代以上")
					);
		foreach($agelist as $item) {
			$html_value = $this->get_html_contents($html_section, "<th>".$item["word"]."</th>", "</tr>");
			if (preg_match("/<td.*?>(.+?)<\/td>/s", $html_value, $preg_result)) {
				$results["business_specialty_employee_age_".$item["id"]] = str_replace(array("\n", "&nbsp;"), "", $preg_result[1]);
			}
		}
		// 利用者の年齢構成 - ～64歳 / 65～74歳 / 75～84歳 / 85～94歳 / 95歳～
		$html_section = $this->get_html_contents($html_contents, "<h3><span>●</span>利用者の年齢構成</h3>", "</table>");
		$agelist = array(
						array("id"=>"under64", "word"=>"～64歳"),
						array("id"=>"65to74", "word"=>"65～74歳"),
						array("id"=>"75to84", "word"=>"75～84歳"),
						array("id"=>"85to94", "word"=>"85～94歳"),
						array("id"=>"over95", "word"=>"95歳～")
					);
		foreach($agelist as $item) {
			$html_value = $this->get_html_contents($html_section, "<th>".$item["word"]."</th>", "</tr>");
			if (preg_match("/<td.*?>(.+?)<\/td>/s", $html_section, $preg_result)) {
				$results["business_specialty_user_age_".$item["id"]] = str_replace(array("\n", "&nbsp;"), "", $preg_result[1]);
			}
		}
		// 定員に対する空き数 - 空き数／定員
		$html_section = $this->get_html_contents($html_contents, "<h3><span>●</span>定員に対する空き数</h3>", "</table>");
		$html_value = $this->get_html_contents($html_section, "<th>空き数／定員</th>", "</tr>");
		if (preg_match("/<td.*?>(.+?)<\/td>/s", $html_value, $preg_result)) {
			$results["business_specialty_notused_rate"] = str_replace(array("\n", "&nbsp;"), "", $preg_result[1]);
		}
	}

	// 詳細 - 所在地・連絡先
	function set_contents_shozaichi($html_contents, &$results) {
		// 該当箇所のhtml取得
		$html_group = $this->get_html_contents($html_contents, "<div class=\"shozaichi\">", "</table>");
		// 介護サービス
		$this->setvalue4th($html_group, $results, "介護サービス", "shozaichi_service");
		// 介護サービス
		$this->setvalue4th($html_group, $results, "事業所番号", "shozaichi_businessno");
	}

	// <th>タグ内の値から値を取得
	function setvalue4th($html, &$results, $start_value, $id) {
		$start_tag = "<th>".$start_value."</th>";
		$end_tag = "</tr>";
		$html_section = $this->get_html_contents($html, $start_tag, $end_tag);
		if ($html_section=="") {
			return;
		}
		if (preg_match("/<td.*?>(.+?)<\/td>/s", $html_section, $preg_result)) {
			$results[$id] = str_replace(array("\n", "\r", "\t", "<div>", "</div>"), "", $preg_result[1]);
		}
	}

	// <th>タグ内にある abbr属性から値を取得
	function setvalue4abbr($html, &$results, $start_value, $id) {
		$start_tag = "<th  abbr='".$start_value;	// ."'";
		$end_tag = "</tr>";
		$html_section = $this->get_html_contents($html, $start_tag, $end_tag);
		if ($html_section=="") {
			return;
		}
		if (preg_match("/<td.*?>(.+?)<\/td>/s", $html_section, $preg_result)) {
			$results[$id] = str_replace("\n", "", $preg_result[1]);
			$results[$id] = str_replace(array("<br>", "<br />"), "\n", $results[$id]);
		}
	}

	// <th>タグ内にある abbr属性から「あり/なし」画像のテキスト取得
	function setvalue_ico_jigyosho($html, &$results, $start_value, $id) {
		$start_tag = "<th  abbr='".$start_value;	// ."'";
		$end_tag = "</tr>";
		$html_section = $this->get_html_contents($html, $start_tag, $end_tag);
		if ($html_section=="") {
			return;
		}
		if (preg_match("/<td.*?>(.+?)<\/td>/s", $html_section, $preg_result)) {
			$results[$id] = $this->gettext_ico_jigyosho($preg_result[1]);
		}
	}

	// 「あり/なし」画像名から、テキスト取得
	function gettext_ico_jigyosho($value) {
		$retval = "";
		if (strpos($value, "ico_jigyosho_ari.gif") > 0) {
			$retval = "1";		// あり
		} else if (strpos($value, "ico_jigyosho_nashi.gif") > 0) {
			$retval = "2";		// なし
		} else if (strpos($value, "ico_jigyosho_tashounashi.gif") > 0) {
			$retval = "0";		// データなし
		}
		return $retval;
	}

	// 指定した開始/終了タグ内のhtmlを取得
	function get_html_contents($html, $start_tag, $end_tag) {
		$retval = "";
		$pos_start = strpos($html, $start_tag);
		if ($pos_start!==FALSE) {
			$pos_end = strpos($html, $end_tag, $pos_start)+strlen($end_tag);
			$retval = substr($html, $pos_start, ($pos_end-$pos_start));
		}
		return $retval;
	}

	function get_mappings($db, $servicecd) {
		$retval = array();
		$sql = "SELECT servicecd, source, target FROM kaigokensaku_mappings WHERE servicecd = '{servicecd}'";
		$sql = str_replace("{servicecd}", $servicecd, $sql);

		$results = $db->exec_select($sql);

		if (is_array($results)) {
			$retval = $results;
		}
		return $retval;
	}

	function update_kaigokensaku_business($db, $datas, $mappings) {
		$sql = "SELECT business_id FROM kaigokensaku_business WHERE business_id = '{business_id}'";
		$sql = str_replace("{business_id}", $datas["business_id"], $sql);
		$results = $db->exec_select($sql);

		$sql = "";
		if (count($results)>0) {
			$sql_values = "";
			foreach ($mappings as $mapping) {
				$itemno = str_replace("item", "", $mapping["target"]);
				if (in_array($mapping["target"], array("business_id", "servicecd"))
					|| $itemno <= 303) {
					$value = $this->get_mapping_data($mapping, $datas);
					if ($value) {
						$sql_values .= $mapping["target"]."='".mysql_escape_string($value)."',";
					} else {
						$sql_values .= $mapping["target"]."=NULL,";
					}
				}
			}
			if ($sql_values) {
				$sql_values .= "modified=NOW()";

				$sql = "UPDATE kaigokensaku_business SET {values} WHERE business_id = '{id}'";
				$sql = str_replace("{id}", $results[0]["business_id"], $sql);
				$sql = str_replace("{values}", $sql_values, $sql);
			}
		} else {
			$sql_items = "";
			$sql_values = "";
			foreach ($mappings as $mapping) {
				$itemno = str_replace("item", "", $mapping["target"]);
				if (in_array($mapping["target"], array("business_id", "servicecd"))
					|| $itemno <= 303) {
					$value = $this->get_mapping_data($mapping, $datas);
					if ($value) {
						$sql_items .= $mapping["target"].",";
						$sql_values .= "'".mysql_escape_string($value)."',";
					}
				}
			}
			if ($sql_values) {
				$sql_items .= "created,modified";
				$sql_values .= "NOW(),NOW()";

				$sql = "INSERT INTO kaigokensaku_business ({items}) VALUES ({values})";
				$sql = str_replace("{items}", $sql_items, $sql);
				$sql = str_replace("{values}", $sql_values, $sql);
			}
		}

		if ($sql) {
			$results = $db->exec_query($sql);
			if (!$results) {
				Util::putlog("[update_kaigokensaku_business insert]".print_r($datas, true).print_r($mappings, true), "error");
			}
		}
	}

	function update_kaigokensaku_business_office($db, $datas, $mappings) {
		$sql = "SELECT business_id FROM kaigokensaku_business_office WHERE business_id = '{business_id}'";
		$sql = str_replace("{business_id}", $datas["business_id"], $sql);
		$results = $db->exec_select($sql);

		$sql = "";
		if (count($results)>0) {
			$sql_values = "";
			foreach ($mappings as $mapping) {
				$itemno = str_replace("item", "", $mapping["target"]);
				if ($itemno >= 304 && $itemno <= 404) {
					$value = $this->get_mapping_data($mapping, $datas);
					if ($value) {
						$sql_values .= $mapping["target"]."='".mysql_escape_string($value)."',";
					} else {
						$sql_values .= $mapping["target"]."=NULL,";
					}
				}
			}
			if ($sql_values) {
				$sql_values .= "modified=NOW()";

				$sql = "UPDATE kaigokensaku_business_office SET {values} WHERE business_id = '{id}'";
				$sql = str_replace("{id}", $results[0]["business_id"], $sql);
				$sql = str_replace("{values}", $sql_values, $sql);
			}
		} else {
			$sql_items = "";
			$sql_values = "";
			foreach ($mappings as $mapping) {
				$itemno = str_replace("item", "", $mapping["target"]);
				if ($itemno >= 304 && $itemno <= 404) {
					$value = $this->get_mapping_data($mapping, $datas);
					if ($value) {
						$sql_items .= $mapping["target"].",";
						$sql_values .= "'".mysql_escape_string($value)."',";
					}
				}
			}
			if ($sql_values) {
				$sql_items .= "created,modified";
				$sql_values .= "NOW(),NOW()";

				$sql = "INSERT INTO kaigokensaku_business_office (business_id, {items}) VALUES ('{id}', {values})";
				$sql = str_replace("{id}", $datas["business_id"], $sql);
				$sql = str_replace("{items}", $sql_items, $sql);
				$sql = str_replace("{values}", $sql_values, $sql);
			}
		}


		if ($sql) {
			$results = $db->exec_query($sql);
		}
	}

	function update_kaigokensaku_business_staff($db, $datas, $mappings) {
		$sql = "SELECT business_id FROM kaigokensaku_business_staff WHERE business_id = '{business_id}'";
		$sql = str_replace("{business_id}", $datas["business_id"], $sql);
		$results = $db->exec_select($sql);

		$sql = "";
		if (count($results)>0) {
			$sql_values = "";
			foreach ($mappings as $mapping) {
				$itemno = str_replace("item", "", $mapping["target"]);
				if ($itemno >= 405 && $itemno <= 690) {
					$value = $this->get_mapping_data($mapping, $datas);
					if ($value) {
						$sql_values .= $mapping["target"]."='".mysql_escape_string($value)."',";
					} else {
						$sql_values .= $mapping["target"]."=NULL,";
					}
				}
			}
			if ($sql_values) {
				$sql_values .= "modified=NOW()";

				$sql = "UPDATE kaigokensaku_business_staff SET {values} WHERE business_id = '{id}'";
				$sql = str_replace("{id}", $results[0]["business_id"], $sql);
				$sql = str_replace("{values}", $sql_values, $sql);
			}
		} else {
			$sql_items = "";
			$sql_values = "";
			foreach ($mappings as $mapping) {
				$itemno = str_replace("item", "", $mapping["target"]);
				if ($itemno >= 405 && $itemno <= 690) {
					$value = $this->get_mapping_data($mapping, $datas);
					if ($value) {
						$sql_items .= $mapping["target"].",";
						$sql_values .= "'".mysql_escape_string($value)."',";
					}
				}
			}
			if ($sql_values) {
				$sql_items .= "created,modified";
				$sql_values .= "NOW(),NOW()";

				$sql = "INSERT INTO kaigokensaku_business_staff (business_id, {items}) VALUES ('{id}', {values})";
				$sql = str_replace("{id}", $datas["business_id"], $sql);
				$sql = str_replace("{items}", $sql_items, $sql);
				$sql = str_replace("{values}", $sql_values, $sql);
			}
		}

		if ($sql) {
			$results = $db->exec_query($sql);
		}
	}

	function update_kaigokensaku_business_service($db, $datas, $mappings) {
		$sql = "SELECT business_id FROM kaigokensaku_business_service WHERE business_id = '{business_id}'";
		$sql = str_replace("{business_id}", $datas["business_id"], $sql);
		$results = $db->exec_select($sql);

		$sql = "";
		if (count($results)>0) {
			$sql_values = "";
			foreach ($mappings as $mapping) {
				$itemno = str_replace("item", "", $mapping["target"]);
				if ($itemno >= 691 && $itemno <= 1238) {
					$value = $this->get_mapping_data($mapping, $datas);
					if ($value) {
						$sql_values .= $mapping["target"]."='".mysql_escape_string($value)."',";
					} else {
						$sql_values .= $mapping["target"]."=NULL,";
					}
				}
			}
			if ($sql_values) {
				$sql_values .= "modified=NOW()";

				$sql = "UPDATE kaigokensaku_business_service SET {values} WHERE business_id = '{id}'";
				$sql = str_replace("{id}", $results[0]["business_id"], $sql);
				$sql = str_replace("{values}", $sql_values, $sql);
			}
		} else {
			$sql_items = "";
			$sql_values = "";
			foreach ($mappings as $mapping) {
				$itemno = str_replace("item", "", $mapping["target"]);
				if ($itemno >= 691 && $itemno <= 1238) {
					$value = $this->get_mapping_data($mapping, $datas);
					if ($value) {
						$sql_items .= $mapping["target"].",";
						$sql_values .= "'".mysql_escape_string($value)."',";
					}
				}
			}
			if ($sql_values) {
				$sql_items .= "created,modified";
				$sql_values .= "NOW(),NOW()";

				$sql = "INSERT INTO kaigokensaku_business_service (business_id, {items}) VALUES ('{id}', {values})";
				$sql = str_replace("{id}", $datas["business_id"], $sql);
				$sql = str_replace("{items}", $sql_items, $sql);
				$sql = str_replace("{values}", $sql_values, $sql);
			}
		}

		if ($sql) {
			$results = $db->exec_query($sql);
		}
	}

	function update_kaigokensaku_business_price($db, $datas, $mappings) {
		$sql = "SELECT business_id FROM kaigokensaku_business_price WHERE business_id = '{business_id}'";
		$sql = str_replace("{business_id}", $datas["business_id"], $sql);
		$results = $db->exec_select($sql);

		$sql = "";
		if (count($results)>0) {
			$sql_values = "";
			foreach ($mappings as $mapping) {
				$itemno = str_replace("item", "", $mapping["target"]);
				if ($itemno >= 1239 && $itemno <= 1501) {
					$value = $this->get_mapping_data($mapping, $datas);
					if ($value) {
						$sql_values .= $mapping["target"]."='".mysql_escape_string($value)."',";
					} else {
						$sql_values .= $mapping["target"]."=NULL,";
					}
				}
			}
			if ($sql_values) {
				$sql_values .= "modified=NOW()";

				$sql = "UPDATE kaigokensaku_business_price SET {values} WHERE business_id = '{id}'";
				$sql = str_replace("{id}", $results[0]["business_id"], $sql);
				$sql = str_replace("{values}", $sql_values, $sql);
			}
		} else {
			$sql_items = "";
			$sql_values = "";
			foreach ($mappings as $mapping) {
				$itemno = str_replace("item", "", $mapping["target"]);
				if ($itemno >= 1239 && $itemno <= 1501) {
					$value = $this->get_mapping_data($mapping, $datas);
					if ($value) {
						$sql_items .= $mapping["target"].",";
						$sql_values .= "'".mysql_escape_string($value)."',";
					}
				}
			}
			if ($sql_values) {
				$sql_items .= "created,modified";
				$sql_values .= "NOW(),NOW()";

				$sql = "INSERT INTO kaigokensaku_business_price (business_id, {items}) VALUES ('{id}', {values})";
				$sql = str_replace("{id}", $datas["business_id"], $sql);
				$sql = str_replace("{items}", $sql_items, $sql);
				$sql = str_replace("{values}", $sql_values, $sql);
			}
		}

		if ($sql) {
			$results = $db->exec_query($sql);
		}
	}

	function update_kaigokensaku_business_other($db, $datas, $mappings) {
		$sql = "SELECT business_id FROM kaigokensaku_business_other WHERE business_id = '{business_id}'";
		$sql = str_replace("{business_id}", $datas["business_id"], $sql);
		$results = $db->exec_select($sql);

		$sql = "";
		if (count($results)>0) {
			$sql_values = "";
			foreach ($mappings as $mapping) {
				$itemno = str_replace("item", "", $mapping["target"]);
				if ($itemno >= 1502 && $itemno <= 1559) {
					$value = $this->get_mapping_data($mapping, $datas);
					if ($value) {
						$sql_values .= $mapping["target"]."='".mysql_escape_string($value)."',";
					} else {
						$sql_values .= $mapping["target"]."=NULL,";
					}
				}
			}
			if ($sql_values) {
				$sql_values .= "modified=NOW()";

				$sql = "UPDATE kaigokensaku_business_other SET {values} WHERE business_id = '{id}'";
				$sql = str_replace("{id}", $results[0]["business_id"], $sql);
				$sql = str_replace("{values}", $sql_values, $sql);
			}
		} else {
			$sql_items = "";
			$sql_values = "";
			foreach ($mappings as $mapping) {
				$itemno = str_replace("item", "", $mapping["target"]);
				if ($itemno >= 1502 && $itemno <= 1559) {
					$value = $this->get_mapping_data($mapping, $datas);
					if ($value) {
						$sql_items .= $mapping["target"].",";
						$sql_values .= "'".mysql_escape_string($value)."',";
					}
				}
			}
			if ($sql_values) {
				$sql_items .= "created,modified";
				$sql_values .= "NOW(),NOW()";

				$sql = "INSERT INTO kaigokensaku_business_other (business_id, {items}) VALUES ('{id}', {values})";
				$sql = str_replace("{id}", $datas["business_id"], $sql);
				$sql = str_replace("{items}", $sql_items, $sql);
				$sql = str_replace("{values}", $sql_values, $sql);
			}
		}

		if ($sql) {
			$results = $db->exec_query($sql);
		}
	}

	function get_mapping_data($mapping, $datas) {
		$retval = "";
		$source = explode(",", $mapping["source"]);
		if (is_array($source)) {
			foreach ($source as $id) {
				$retval .= (empty($retval) ? "" : ",") . (array_key_exists($id, $datas) ? $datas[$id] : "");
			}
		}
		return $this->mapping_option($retval, $mapping["target"]);
	}

	// クロールしたデータを加工
	function mapping_option($value, $mapping_target) {
		switch($mapping_target) {
			case "item6":
				$value = str_replace("Tel：", "", $value);
				break;
			case "item7":
				$value = str_replace("Fax：", "", $value);
				break;
			case "item314":
			case "item367":
				$value = str_replace(array("<div class=viewTypeUrl>", "</div>"), "", $value);
				break;
			case "item21":
			case "item317":
			case "item375":
			case "item376":
			case "item377":
			case "item378":
			case "item379":
			case "item1236":
				$pos_start = strpos($value, "<div");
				if ($pos_start!==FALSE) {
					$value = substr($value, 0, $pos_start);
				}
				break;
			case "servicecd":
				$value = $this->get_wam_servicecd($value);
				break;
		}
		return $value;
	}

	function get_wam_servicecd($kaigokennsaku_servicecd) {
		global $arr_servicecd;

		$wam_servicecd = "";
		foreach ($arr_servicecd as $servicecd) {
			if ($servicecd["kaigokensaku"]==$kaigokennsaku_servicecd) {
				$wam_servicecd = $servicecd["wam"];
				break;
			}
		}
		return  $wam_servicecd;
	}
}

/**
* DBアクセスクラス
*
*/
class DbAccess {
	private $db;

	function dbconnect() {
		global $dbinfo;
		$this->db = mysql_connect($dbinfo["server"], $dbinfo["username"], $dbinfo["password"]);
		mysql_select_db($dbinfo["database"], $this->db);
		mysql_set_charset("utf8");
	}

	function dbclose() {
		mysql_close($this->db);
	}

	function exec_query($sql) {
//Util::putlog("[exec_query]".$sql);
		if (mysql_query($sql)) {
			return true;
		} else {
			Util::putlog("[exec_query]".mysql_error()."|".$sql, "sqlerror");
			return false;
		}
	}

	function exec_select($sql) {
//Util::putlog("[exec_select]".$sql);
		$retval = array();
		$result = mysql_query($sql);
		if ($result) {
			while ($row = mysql_fetch_assoc($result)) {
				$retval[] = $row;
			}
		} else {
			Util::putlog("[exec_select]".mysql_error()."|".$sql, "sqlerror");
			return false;
		}
		return $retval;
	}
}

// ==============================
// 共通クラス
// ==============================
class Util {
	// ログ出力
	static function putlog($message, $mode="debug") {
		global $debug_mode;

		if (!$debug_mode && $mode=="debug") {
			return;
		}

		global $dir_logfile;
		try {
			$dir = $dir_logfile;
			if (!is_dir($dir)) {
				mkdir($dir);
			}
			$filename = $dir."/".date("Ymd").".log";
			$date = date("Y-m-d H:i:s");
			$output = $date."\t".$mode."\t".$message.PHP_EOL;
			$fp = fopen($filename, "a");
			flock($fp, LOCK_EX);
			fputs($fp, $output);
			fclose($fp);
		} catch(Exception $e) {
			print $e->getMessage();
		}
	}

	// htmlファイル出力
	static function puthtml($name, $contents) {
		global $dir_logfile;
		try {
			$dir = $dir_logfile."/html";
			if (!is_dir($dir)) {
				mkdir($dir);
			}
			$filename = $dir."/".$name.".".date("YmdHis").ceil(microtime(true)*1000).".html";
			$date = date("H:i:s");
			$fp = fopen($filename, "a");
			flock($fp, LOCK_EX);
			fputs($fp, $contents);
			fclose($fp);
		} catch(Exception $e) {
			print $e->getMessage();
		}
	}

	function sendmail($from, $to, $subject, $body) {
		$headers = "Mime-Version: 1.0\n";
		$headers .= "Content-Type: text/plain;charset=UTF-8\n";
		$headers .= "From: ".$from."\n";
		$subject = "=?UTF-8?B?".base64_encode($subject)."?=";
		return mail($to, $subject, $body, $headers);
	}
}
