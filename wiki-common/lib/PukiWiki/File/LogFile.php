<?php

namespace PukiWiki\File;

use DirectoryIterator;
use PukiWiki\File\File;
use Exception;
use PukiWiki\Auth\Auth;
use PukiWiki\File\AbstractFile;
use PukiWiki\NetBios;
use PukiWiki\Spam\ProxyChecker;
use PukiWiki\Utility;
use PukiWiki\File\DiffFile;
use PukiWiki\Factory;

/**
 * ファイルの読み書きを行うクラス
 */
class LogFile extends AbstractFile{
	/**
	 * ログのキーの保持期間（30日）
	 */
	const LOG_LIFE_TIME = 18144000;
	/**
	 * ログの最大エントリ数（500もいらないと思う）
	 */
	const LOG_MAX_ENTRIES = 500;
	/**
	 * ログの種類
	 */
	protected static $kind_no = array(
	//  'default'   => 0,
		'update'    => 1,
		'download'  => 2,
		'cmd'       => 3,
		'login'     => 4,
		'check'     => 5,
	);
	/**
	 * ログの表示フィールド定義
	 * 先頭 @ の項目は、ログには保存されていない項目(表示用)
	 */
	protected static $field = array(
		// 定義順は、デフォルト(all)表示順
		// idx                    0  1  2  3  4  5
		'ts'            => array( 1, 1, 1, 1, 1, 1),    // タイムスタンプ (UTIME)
		'@diff'         => array( 0, 1, 0, 0, 0, 0),    // 差分内容
		'@guess_diff'   => array( 0, 1, 0, 0, 0, 1),    // 推測差分
		'ip'            => array( 1, 1, 1, 1, 1, 1),    // IPアドレス
		'host'          => array( 1, 1, 1, 1, 1, 1),    // ホスト名 (FQDN)
		'@guess'        => array( 1, 1, 1, 0, 0, 0),    // 推測
		'auth_api'      => array( 0, 0, 0, 0, 1, 1),    // 認証API
		'user'          => array( 1, 1, 1, 1, 1, 1),    // ユーザ名(認証済)
		'ntlm'          => array( 1, 1, 1, 1, 0, 0),    // ユーザ名(NTLM認証)
		'proxy'         => array( 1, 1, 1, 1, 0, 0),    // Proxy情報
		'ua'            => array( 1, 1, 1, 1, 1, 1),    // ブラウザ情報 (USER-AGENT)
		'del'           => array( 0, 1, 0, 0, 0, 0),    // 削除フラグ
		'sig'           => array( 0, 1, 0, 0, 0, 0),    // 署名(曖昧)
		'file'          => array( 0, 0, 1, 0, 0, 0),    // ファイル名
		'cmd'           => array( 0, 0, 0, 1, 0, 0),    // コマンド名
		'page'          => array( 1, 1, 1, 0, 0, 0),    // ページ名
		'local_id'      => array( 0, 0, 0, 0, 1, 0),    // OpenIDの場合のみ設定される
	);
	/**
	 * 対象ディレクトリ
	 */
	public static $dir = LOG_DIR;
	/**
	 * ログの種類
	 */
//	public static $kind;
	/**
	 * コンストラクタ
	 * @param string $page
	 */
	public function __construct($page) {
		if (empty($this->kind)) throw new Exception('class :'.$class.' does not defined $kind value.');
		// ログ設定
		global $log;
		$this->config = $log;

		if (!$this->isWiki) {
			if (empty($page)){
				throw new Exception('Page name is missing!');
			}
			// ページ名
			$this->page = $page;

			parent::__construct(self::$dir . $this->kind . '/' . Utility::encode($page) . '.txt');
		}else{
			parent::__construct(DATA_DIR .Utility::encode(':log/' . $this->kind) . '.txt');
		}
	}
	/**
	 * ファイル一覧を取得
	 * @return array
	 */
	public static function exists($pattern = ''){
		// 継承元のクラス名を取得（PHPは、__CLASS__で派生元のクラス名が取得できない）
		$class =  get_called_class();
		$dir = self::$dir . $class::$log_dir;
		// クラスでディレクトリが定義されていないときは処理しない。(AuthFile.phpなど）
		if ( empty($dir)) return array();
		// パターンが指定されていない場合は、クラスで定義されているデフォルトのパターンを使用
		if ( empty($pattern) ) $pattern = $class::$pattern;
		// 継承元のクラスの定数をパラメーターとして与える
		foreach (new DirectoryIterator($dir) as $fileinfo) {
			$filename = $fileinfo->getFilename();
			$matches = array();
			if ($fileinfo->isFile() && preg_match($pattern, $filename, $matches)){
				$ret[] = Utility::decode($matches[1]);
			}
		}
		return $ret;
	}
	/**
	 * ログの存在チェック
	 */
	public function has(){
		if (!$this->config[$this->kind]['use']) return false;
		return parent::has();
	}
	/**
	 * ログ件数
	 */
	public function count(){
		return count(parent::get());
	}
	/**
	 * 共通チェック
	 */
	private function log_common_check()
	{
		global $log_ua;

		// 認証中のユーザ名
		$username = Auth::check_auth();

		// 認証済の場合
		if ($this->config['auth_nolog'] && !empty($username)) return null;

		// タイムスタンプ
		$utime = UTIME;
		// リモートIPを取得
		$ip = Utility::getRemoteIp();

		if (isset($this->config[$this->kind]['nolog_ip'])) {
			// ロギング対象外IP
			foreach ($this->config[$this->kind]['nolog_ip'] as $nolog_ip) {
				if ($ip == $nolog_ip) return null;
			}
		}
		$rc = array();
		$field = self::set_fieldname();

		foreach ($field as $key) {
			switch ($key) {
				case 'ts': // タイムスタンプ (UTIME)
					$rc[$key] = (int)$utime;
					break;
				case 'ip': // IPアドレス
					$rc[$key] = $ip;
					break;
				case 'host': // ホスト名 (FQDN)
					$rc[$key] = gethostbyaddr($ip);
					break;
				case 'auth_api': // 認証API名
					//$obj = new auth_api();
					//$msg = $obj->auth_session_get();
					$rc[$key] = (isset($msg['api']) && ! empty($username)) ? 'plus' : null;
					break;
				case 'local_id':
					$rc[$key] = isset($msg['local_id']) ? null : $msg['local_id'];
					break;
				case 'user': // ユーザ名(認証済)
					$rc[$key] = $username;
					break;
				case 'ntlm': // ユーザ名(NTLM認証)
					if (self::netbios_scope_check($ip,gethostbyaddr($ip))) {
						$obj_nbt = new NetBios($ip);
						$rc[$key] = $obj_nbt->username;
						unset($obj_nbt);
					} else {
						$rc[$key] = null;
					}
					break;
				case 'proxy': // Proxy情報
					$obj_proxy = new ProxyChecker();
					$rc[$key] = $obj_proxy->is_proxy() ? 
						$obj_proxy->get_proxy_info() . '(' . $obj_proxy->get_realip() . ')' :
						null;
					unset($obj_proxy);
					break;
				case 'ua': // ブラウザ情報
					$rc[$key] = $log_ua;
					break;
				case 'del': // 削除フラグ
					// 更新時は、削除されたか？
					$rc[$key] = ($kind === 'update' && Factory::Wiki($page)->has()) ? null : 'DELETE';
					break;
				case 'sig': // 署名(曖昧)
					$rc[$key] = self::log_set_signature($utime);
					break;
				case 'file': // ファイル名
					$rc[$key] = $this->kind;
					break;
				case 'page':
				case 'cmd':
					$rc[$key] = $this->page;
					break;
			}
		}
		return $rc;
	}
	/**
	 * 署名の特定
	 */
	private function log_set_signature($utime)
	{
		// $utime は、今後、閲覧者の特定などの際にバックアップファイルから
		// 特定することを想定し、含めている。

		if ($this->kind != 'update') return null;

		$diff = new DiffFile($this->page); // 差分ファイル名

		$lines = array();

		if ($diff->has()) {
			// 今回更新行のみ抽出
			foreach($diff->get() as $_src) {
				if (substr($_src,0,1) == '+') $lines[] = substr($_src,1);
			}
		} else {
			// 新規ページの全てが対象
			$lines = Factory::Wiki($page)->get();
		}

		return Auth::get_signature($lines);
	}
	/**
	 * 設定項目名を設定
	 * @static
	 */
	private function set_fieldname()
	{
		$idx = isset(self::$kind_no[$this->kind]) ? self::$kind_no[$this->kind] : 0;

		$rc = array();
		foreach(self::$field as $_field => $sw) {
			if ($sw[$idx] == 0) continue;
			if ($_field == 'page' && !isset($this->config[$this->kind]['file'])) continue;
			$rc[] = $_field;
		}

		return $rc;
	}
	
	/**
	 * ログ記入
	 * @param $value 未使用（何も入れないこと）
	 * @param $keeptimestamp 未使用（何も入れないこと）
	 */
	public function set($value = '', $keeptimestamp = false){
		// 設定
		$config = $this->config[$this->kind];
		
		// ログを取らない場合書き込まない
		if (!$config['use']) return;
		// 書き込むデーターを取得
		$rc = self::log_common_check();
		// ない場合終了
		if (empty($rc)) return;
		// ログを読み込む
		$lines = self::get(false);
		// 行数
		$count = count($lines);

		// 更新するキーを取得
		if (! empty($config['updtkey'])) {
			// 最低限記録するキー
			$mustkey = isset($config['mustkey']) ? $config['mustkey'] : 0;
			// 保存するキーを定義から取得
			$_key = explode(':',$config['updtkey']);
			// 設定項目名を取得
			$name = self::set_fieldname();

			// 更新フラグ
			$put = false;
			// 行の分解
			for($i=0;$i<$count;$i++) {
				// ログの１行を配列に変換した後、項目名を付与する
				// $line = array(
				//     'ts' => 000000,
				//     'ip' => 127.0.0.1
				//     ...
				// );
				// みたいな感じになる
				$line = self::line2field($lines[$i],$name);

				if (isset($line['ts']) && $line['ts'] <= UTIME - self::LOG_LIFE_TIME){
					// 一定期間過ぎたエントリは削除
					unset($lines[$i]);
					continue;
				}

				// 行書き換えフラグ
				$sw_update = true;

				// 列の分解
				foreach($_key as $idx) {
					// 書き込む前のデーターと異なっていた場合
					if (isset($rc[$idx]) && isset($line[$idx]) && $rc[$idx] !== $line[$idx]) {
						$sw_update = false;
						break;
					}
/*
					if (empty($rc[$idx]) || empty($line[$idx])) {
						$sw_update = false;
						break;
					}
					if ($rc[$idx] !== $line[$idx]) {
						$sw_update = false;
						break;
					}
*/
				}
				
				if ($sw_update) {
					// 書き換え
					$lines[$i] = self::array2table($rc);
					$put = true;
					break;
				}
			}

			unset($i);

			
			if (! $put) {
				if ($mustkey) {
					if (self::log_mustkey_check($_key,$data)) {
						$lines[] = self::array2table($rc);
					}
				} else {
					$lines[] = self::array2table($rc);
				}
			}
		} else {
			// 新規データー
			$lines[] = self::array2table( $rc );
		}

		// 配列の長さ制限
		if ( $count > self::LOG_MAX_ENTRIES) {
			$i = 0;
			// 古いエントリから削除するため配列を反転
			foreach (array_reverse($lines) as $line){
				if ($i > self::LOG_MAX_ENTRIES) break;
				$ret[] = $line;
				$i++;
			}
			// 戻す
			$lines = array_reverse($ret);
		}

		// 見做しユーザ
		if ($this->kind == 'update' && $this->config['guess_user']['use']) {
			log_put_guess($rc);
		}
		// 保存（空行は削除）
		parent::set(array_filter($lines));
	}
	/**
	 * ユーザを推測する
	 * @static
	 */
	private static function guess_user($user,$ntlm,$sig)	{
		if (!empty($user)) return $user; // 署名ユーザ
		if (!empty($ntlm)) return $ntlm; // NTLM認証ユーザ
		if (!empty($sig))  return $sig;  // 本人の署名
		return null;
	}
	
	private static function log_mustkey_check($key,$data)
	{
		foreach($key as $idx) {
			if (empty($data[$idx])) return false;
		}
		return true;
	}
	/**
	 * 配列データを PukiWiki 表形式データに変換
	 * @static
	 */
	private static function array2table($data)
	{
		$rc = '';
		foreach ($data as $x1) {
			$rc .= '|'.$x1;
		}
		$rc .= "|";
		return $rc;
	}

	/**
	 * PukiWiki 表形式データかの判定
	 * @static
	 */
	private static function is_table($line)
	{
		$x = trim($line);
		if (substr($x,0,1) !== '|') return FALSE;
		if (substr($x,-1)  !== '|') return FALSE;
		return TRUE;
	}

	/**
	 * PukiWiki 表形式データを配列データに変換
	 * @static
	 */
	private static function table2array($x)
	{
		if (!self::is_table($x)) return array();
		return explode('|', substr($x,1,-1));
	}

	/**
	 * ログの１行を配列に変換した後、項目名を付与する
	 * @static
	 */
	private static function line2field($line,$name)
	{
		$_fld = self::table2array($line);
		$i = 0;
		$rc = array();
		foreach($name as $_name) {
			if (substr($_name,0,1) === '@') continue;

			$rc[$_name] = isset($_fld[$i]) ? $_fld[$i] : null;
			$i++;
		}
		return $rc;
	}
	/**
	 * NetBIOS の適用範囲決定
	 */
	private function netbios_scope_check($ip,$host)
	{
		static $ip_pattern = '/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?:\/(.+))?$/';

		if (!$this->config['auth_netbios']['use']) return FALSE;

		$l_ip = ip2long($ip);
		$valid = (is_long($l_ip) and long2ip($l_ip) == $ip); // valid ip address

		$matches = array();
		foreach ($this->config['auth_netbios']['scope'] as $network)
		{
			if ($valid and preg_match($ip_pattern,$network,$matches))
			{
				$l_net = ip2long($matches[1]);
				$mask = array_key_exists(2,$matches) ? $matches[2] : 32;
				$mask = is_numeric($mask) ?
					pow(2,32) - pow(2,32 - $mask) : // "10.0.0.0/8"
					ip2long($mask);                 // "10.0.0.0/255.0.0.0"
				if (($l_ip & $mask) == $l_net) return TRUE;
			} else {
				if (preg_match('/'.preg_quote($network,'/').'/',$host)) return FALSE;
			}
		}
		return FALSE;
	}
}