<?php
class Office extends Eloquent {
    protected $table = 'kkm_new';
    protected $primaryKey = 'id';
    const NEARBY = 0.005;
    static $NEIGHBOURS = 1;

    protected $hidden = ['geo'];

    protected static $OPT_MAP = [
		// This taken from /oya/frontend/view/partial/mapopt.yml
		// Be sure sync them when there is changes
		// Mapping a checkbox value with the service_id values
		1 => [50 ],     // 介護認定を相談する
		2 => [1, 91],   // 食事・排泄・入浴介助など
		3 => [3 ],      // 看護を伴う介助
		4 => [2 ],      // （重度の方向け）入浴専用車両での入浴介助
		5 => [4 ],      // 家でリハビリテーション
		6 => [10, 11],  // 日帰りでみてもらう
		7 => [21, 22 ],     // 宿泊でみてもらう
		8 => [92 ],     // 認知症でもOK
		9 => [12],  // 通いでリハビリテーション
		10 => [32, 95],    // 介護サービス付き住宅
		11 => [96 ],    // （認知症の方向け）グループホーム
		12 => [63 ],    // 長期にわたる医療入院
		13 => [62 ],    // 退院から自宅に戻るまでのリハビリ先
		14 => [61, 94 ],    // （重度の方向け）終身入居用医療機関（特養）
		15 => [41 ],    // レンタル
		16 => [65 ],    // 購入
	];
	protected static $SECTIONS = [
		50 => 'a',
		1  => 'b',
		2  => 'd',
		3  => 'c',
		4  => 'e',
		91 => 'b',
		11 => 'g',
		12 => 'g',
		10 => 'g',
		92 => 'f',
		41 => 'k',
		65 => 'l',
		/* 93 => 'j', */
		21 => 'i',
		22 => 'i',
		61 => 'n',
		62 => 'm',
		63 => 'o',
		32 => 'p',
		96 => 'h',
		94 => 'n',
		95 => 'p',
	];
	protected static $COLORING = [
		'yellow' => ['居宅', [
			50 => '居宅介護支援',
		]],
		'pink' => ['訪問系', [
			1 => '訪問介護',
			2 => '訪問入浴',
			3 => '訪問看護',
			4 => '訪問リハビリ',
			91 => '夜間対応型訪問看護',
		]],
		'orange' => ['通所系', [
			11 => '通所介護',
			12 => '通所リハビリ',
			10 => '療養通所介護',
			92 => '認知症対応型通所介護',
		]],
		'red' => ['福祉用具', [
			41 => '福祉用具貸与',
			65 => '特定福祉用具販売',
		]],
		//'purple' => ['小規模', [
		//    93 => '小規模多機能型居宅介護',
		//]],
		'blue' => ['ショート', [
			21 => '短期入所生活介護',
			22 => '短期入所療養介護',
		]],
		'tree' => ['施設系', [
			61 => '介護老人福祉施設',
			62 => '介護老人保健施設',
			63 => '介護療養型医療施設',
			32 => '特定施設入居者生活介護',
		]],
		'green' => ['GH', [
			96 => '認知症対応型共同生活介護',
		]],
		'gray' => ['地域密着系', [
			94 => '地域密着型介護老人福祉施設入居者生活介護',
			95 => '地域密着型特定施設入居者生活介護',
		]],
	];

	// For table kkm_new
    protected $fieldsMap = [
		'item361 AS postal',
        'item363 AS address',
        'item371 AS registered_no',
        'latitude AS lat',
        'longitude AS lng',
        'servicecd AS h_type',
        'item27 AS week',
        'item28 AS sat',
        'item29 AS sun',
        'item30 AS holiday',
        'item31 AS scheduled',
        'item6 AS tel',
        'item7 AS fax',
        'item360 AS name',
        'item22 AS area_1',
        'item23 AS area_2',
        'latitude AS lat',
        'longitude AS lng',
		'item21 AS started',
		'item9 AS created',
    ];

    public function buildResult($query, $page, $terminal) {
		$pager = $this->pager($query, $page, $terminal);
		foreach ($pager['rows'] as $index => $row) {
			$pager['rows'][$index] = $this->toView($row);
        }
        return $pager;
	}

    public function nearbyQuery($lat, $lng, $options) {
        $area = false;
        $query = $this->searchQuery(null, $options);
        $lp = $lat + self::$NEIGHBOURS;
        $np = $lng + self::$NEIGHBOURS;
        $lm = $lat - self::$NEIGHBOURS;
        $nm = $lng - self::$NEIGHBOURS;
        $query->whereRaw("MBRContains(GeomFromText('LineString($lp $np, $lm $nm)'), `geo`)")
            ->orderByRaw("GLength(GeomFromText(CONCAT('LineString($lat $lng, ', X(`geo`), ' ', Y(`geo`), ')')))")
			->select(array_merge($this->fieldsMap, ['id', 'item20']));
		return $query;
    }

    public function extractNearby($lat, $lng, $options, $n) {
		$query = $this->nearbyQuery($lat, $lng, $options);
        $rows = $query
            ->skip(0)
            ->take($n)
            ->get();
        return $rows;
    }

	protected function parseTime($hour) {
		$s = strtr($hour, [
			'時' => ':',
			'分' => '',
			'～' => ' ',
			'~'  => ' ',
		]);
		preg_match('/(\d+):(\d+) (\d+):(\d+)/', $s, $m);
		if (count($m) == 5 && !($m[1] == 0 && $m[2] == 0 && $m[3] == 0 && $m[4] == 0)) {
			array_shift($m);
			return vsprintf('%s時%sd分～%s時%s分', $m);
		} else {
			return false;
		}
	}

	protected function toView($data) {
		if (empty($data)) return null;
		$data['address'] = strip_tags($data['address']);
		$data['color'] = self::coloring($data['h_type']);
		foreach(['sat', 'sun', 'week', 'holiday'] as $k) {
			if (isset($data[$k])) {
				$data[$k] = $this->parseTime($data[$k]);
			}
		}
		return $data;
	}

	public function detail($id) {
		$query = DB::table('kkm_new')
			->select(['*'] + $this->fieldsMap)
			->where('id', '=', $id);
        $query = $this->scopeActive($query);
		return $this->toView($query->first());
	}

    protected function searchQuery($area = '', $options) {
        $query = DB::table('kkm_new');
		$filter = [];
        if (count($options)) {
			foreach ($options as $opt) {
				$service_id = array_get(self::$OPT_MAP, (int)$opt);
				if ($service_id) {
					$filter = array_merge($filter, $service_id);
				}
			}
		}
		if (empty($filter)) {
			$filter = array_keys(self::$SECTIONS);
		}
        $query->whereIn('servicecd', $filter);
		$query->where('latitude', '!=', 0);
		$query->where('longitude', '!=', 0);
        $query = $this->scopeActive($query);
        return $query;
    }

    public function pager($query, $page, $terminal) {
		$limit = Config::get("oya.pager.$terminal");
		$max_nav = Config::get("oya.nav.$terminal");
		$total = min($query->count(), Config::get("oya.limit.$terminal"));
		$from = ($page - 1) * $limit + 1;
		$to = min($from + $limit - 1, $total);
		$max_page = ceil($total / $limit);
		$series = range(1, $max_page);
		if ($max_page > $max_nav) {
			// List page numbers to be shown
			$zoom = ceil(($max_nav - 2) / 2);
			$series = range(
				max($page - $zoom, 1),
				min($page + $zoom, $max_page)
			);
			if (($n_nav = count($series)) < $max_nav) {
				// Add more spaces for navigator
				$first = $series[0];
				$last = $series[$n_nav - 1];
				$adding = $max_nav - $n_nav;
				if ($first == 1 && $last < $max_page) {
					// Extend the ending
					$series = array_merge(
						$series,
						range($last + 1, min($last + $adding, $max_page))
					);
				}
				if ($last == $max_page && $first > 1) {
					// Extend the begining
					$series = array_merge(
						range(max($first - $adding, 1), $first - 1),
						$series
					);
				}
			}
		}
		$rows = $query->skip($from - 1)
			->select(array_merge($this->fieldsMap, ['id', 'item20']))
			->take($limit)
			->get();
		return compact('total', 'from', 'to', 'limit', 'rows', 'page', 'max_page', 'series');
	}

    protected static function coloring($serviceType) {

        foreach (self::$COLORING as $color => $match) {
            list ($group, $types) = $match;
            if (in_array($serviceType, array_keys($types))) {
                $type = $types[$serviceType];
                return compact('color', 'group', 'type');
            }
        }
        return false;
    }

    public static function section($serviceType) {
        return array_get(self::$SECTIONS, $serviceType);
    }

    public function scopeActive($query) {
        return $query->where('del_flag', 0);
    }
}
