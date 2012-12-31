namespace PukiWiki\Lib\File;

class FileUtility{
	public static function get_exsists($dir = DATA_DIR, $pattern = '/^((?:[0-9A-F]{2})+)\.txt$/', $force = false){
		$cache_name = self::EXSISTS_CACHE_PREFIX.md5($dir);
		if ($force){
			$this->cache->removeItem($cache_name);
		}else if ($this->cache->hasItem($cache_name)){
			return $this->cache->getItem($cache_name);
		}
		foreach (new DirectoryIterator($this->dir) as $fileinfo) {
			$filename = $fileinfo->getFilename();
			if ($fileinfo->isFile() && preg_match($pattern, $filename, $matches)){
				$aryret[$filename] = decode($matches[1]);
			}
		}
		$this->cache->setItem($cache_name, $aryret);
		return $aryret;
	}
	public static function get_recent(){
		// Get WHOLE page list
		$pages = get_exists();

		// Check ALL filetime
		$recent_pages = array();
		foreach($pages as $page)
			$wiki = FileFactory::Wiki($page);
			if ($page !== $whatsnew && ! $wiki->is_hidden())
				$recent_pages[$page] = $wiki->getTime();

		// Sort decending order of last-modification date
		arsort($recent_pages, SORT_NUMERIC);
		return $recent_pages;
	}
}