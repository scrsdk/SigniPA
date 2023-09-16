<?php
class ipaDistrubution { 
	
	/**
    * Базовый URL-адрес скрипта.
    */
	protected $baseurl;
	/**
    * Базовая папка скрипта.
    */
	protected $basedir;
	/**
    * Папка приложения, в которую будет записан манифест.
    */
	protected $folder;
	/**
    * Название iTunesArtwork, которое является стандартом от Apple(http://developer.apple.com/iphone/library/qa/qa2010/qa1686.html).
    */
	protected $itunesartwork = "iTunesArtwork";
	/**
    * Название приложения, которое можно использовать для HTML-страницы.
    */
	public $appname;
	/**
    * Версия пакета, которую можно использовать для HTML-страницы.
    */
	public $bundleversion;
	/**
    * Mobileprovision
    */
	public $mobileprovision;
	/**
    * Приложение icon, которое можно использовать для HTML-страницы.
    */
	public $appicon;
	/**
    * Ссылка на манифест для iPhone .
    */
	public $applink = "";
	/**
    * Идентификатор пакета, который используется для поиска соответствующего профиля предоставления.
    */
	protected $identiefier;
	/**
    * Имя значка пакета для извлечения файла значков
    */
	public $icon;
	/**
    * Название профиля подготовки для приложения IPA для iPhone .
    */
	public $provisionprofile;
	
	
	/**
    * Инициализируйте IPA и создайте манифест.
    *
    * Строка $ipa - файл IPA, для которого должен быть создан манифест
    */
    public function __construct($ipa, $subfolder){ 
    	$this->baseurl = "http".((!empty($_SERVER['HTTPS'])) ? "s" : "")."://".$_SERVER['SERVER_NAME'];
		$this->basedir = (strpos($_SERVER['REQUEST_URI'],".php")===false?$_SERVER['REQUEST_URI']:dirname($_SERVER['REQUEST_URI'])."/"); 
				
		//Удалить строку запроса из basedir
		if( strpos( $this->basedir, "?" ) > 0 ) {
			$questionmark_position = strpos( $this->basedir, "?" );
			$this->basedir = substr( $this->basedir, 0, $questionmark_position);
		}
		
		$this->makeDir("ipa/" . $subfolder . basename($ipa, ".ipa"));
		
		$this->getPlist($ipa);
		
		$this->createManifest($ipa);
		
		$this->seekMobileProvision($this->identiefier);
		
		$this->getIcon($ipa);
		
		$this->getMobileProvision($ipa);
		
		if (file_exists($this->itunesartwork)) {
			$this->makeImages();	
		}

		$this->cleanUp();
    } 
    
    
    
    //title appnmae
    
    
    
    /**
    * Создайте папку, в которой хранятся файлы манифеста и значков.
    *
    * Строка $dirname название папки
    */
    function makeDir ($dirname) {
    	$this->folder = $dirname;
    	if (!is_dir($dirname)) {
    		if (!mkdir($dirname)) die('Failed to create folder '.$dirname.'... Is the current folder writeable?');
    	}
	}
	
	/**
    * Получите de Plist и iTunesArtwork из файла IPA
    *
    * Строка $ipa - местоположение файла IPA
    */
	function getPlist ($ipa) {
		if (is_dir($this->folder)) {
			$zip = zip_open($ipa);
			if ($zip) {
			  while ($zip_entry = zip_read($zip)) {
			    $fileinfo = pathinfo(zip_entry_name($zip_entry));
			    if ($fileinfo['basename']=="Info.plist"||$fileinfo['basename']==$this->itunesartwork) {
			    	$fp = fopen($fileinfo['basename'], "w");
			    	if (zip_entry_open($zip, $zip_entry, "r")) {
				      $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
				      fwrite($fp,"$buf");
				      zip_entry_close($zip_entry);
				      fclose($fp);
				    }
			    }
			  }
			  zip_close($zip);
			}
		}
	}
	
	/**
    * Создайте иконку (если не в IPA) и обложку itunes из оригинального iTunesArtwork
    */
	function makeImages () {
		if (function_exists("ImageCreateFromJPEG")) {
			$im = @ImageCreateFromJPEG ($this->itunesartwork);
			$x = @getimagesize($this->itunesartwork);
			$iTunesfile = @ImageCreateTrueColor (512, 512);
			@ImageCopyResampled ($iTunesfile, $im, 0, 0, 0, 0, 512, 512, $x[0], $x[1]);
			@ImagePNG($iTunesfile,$this->folder."/itunes.png",0);
			@ImageDestroy($iTunesfile);
			if ($this->icon==null) {
				$iconfile = @ImageCreateTrueColor (57, 57);
				@ImageCopyResampled ($iconfile, $im, 0, 0, 0, 0, 57, 57, $x[0], $x[1]);
				@ImagePNG($iconfile,$this->folder."/icon.png",0);
				@ImageDestroy($iconfile);
				$this->appicon = $this->folder."/icon.png";
			}
		}
	}
	
	
	/**
    * Извлеките файл значка из IPA и поместите его в нужную папку
    */




    function getIcon ($ipa) {

     $zip = zip_open($ipa);
     if ($zip) {
       while ($zip_entry = zip_read($zip)) {
         $fileinfo = pathinfo(zip_entry_name($zip_entry));//echo ($fileinfo['basename']);
         if ($fileinfo['basename']=='AppIcon60x60@3x.png') {//echo $fileinfo['basename'];
             $fp = fopen($this->folder.'/'.$fileinfo['basename'], "w");
             if (zip_entry_open($zip, $zip_entry, "r")) {
               $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
               fwrite($fp,"$buf");
               zip_entry_close($zip_entry);
               fclose($fp);
             }
         $this->appicon = $this->folder."/".'AppIcon60x60@3x.png';
         }
       }
       zip_close($zip);

 }
}

	
	/**
    * Извлеките файл .mobileprovision из IPA и поместите его в нужную папку
    */
	function getMobileProvision ($ipa) {
		if (is_dir($this->folder)) {
			$zip = zip_open($ipa);
			if ($zip) {
			  while ($zip_entry = zip_read($zip)) {
			    $fileinfo = pathinfo(zip_entry_name($zip_entry));
			    if ($fileinfo['basename']=="embedded.mobileprovision") {
			    	$fp = fopen($this->folder.'/'.$fileinfo['basename'], "w");
			    	if (zip_entry_open($zip, $zip_entry, "r")) {
				      $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
				      fwrite($fp,"$buf");
				      zip_entry_close($zip_entry);
				      fclose($fp);
				    }
				$this->mobileprovision = $this->folder."/".$fileinfo['basename'];
			    }
			  }
			  zip_close($zip);
			}
		}
	}
	
	/**
    * Проанализируйте Plist и получите значения для создания манифеста и напишите манифест
    *
    * Строка $ipa - местоположение файла IPA
    */
	function createManifest ($ipa) {
		if (file_exists(dirname(__FILE__).'/cfpropertylist/CFPropertyList.php')) {
			require_once(dirname(__FILE__).'/cfpropertylist/CFPropertyList.php');

			$plist = new CFPropertyList('Info.plist');
			$plistArray = $plist->toArray();
			//var_dump($plistArray);
			$this->identiefier = $plistArray['CFBundleIdentifier'];
			$this->appname = $plistArray['CFBundleDisplayName'];
			$this->bundleversion = $plistArray['CFBundleVersion'];
			$this->icon = ($plistArray['CFBundleIconFile']!=""?$plistArray['CFBundleIconFile']:(count($plistArray['CFBundleIconFile'])>0?$plistArray['CFBundleIconFile'][0]:null));
			
			
			$manifest = '<?xml version="1.0" encoding="UTF-8"?>
			<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
			<plist version="1.0">
			<dict>
				<key>items</key>
				<array>
					<dict>
						<key>assets</key>
						<array>
							<dict>
								<key>kind</key>
								<string>software-package</string>
								<key>url</key>
								<string>'.$this->baseurl.$this->basedir.$ipa.'</string>
							</dict>
							'.(file_exists($this->folder.'/itunes.png')?'<dict>
								<key>kind</key>
								<string>full-size-image</string>
								<key>needs-shine</key>
								<false/>
								<key>url</key>
								<string>'.$this->baseurl.$this->basedir.$this->folder.'/AppIcon60x60@3x.png</string>
							</dict>':'').'
							'.(file_exists($this->folder.'/AppIcon60x60@3x.png')?'<dict>
								<key>kind</key>
								<string>display-image</string>
								<key>needs-shine</key>
								<false/>
								<key>url</key>
								<string>'.$this->baseurl.$this->basedir.$this->folder.'/'.($this->icon==null?'AppIcon60x60@3x.png':$this->icon).'</string>
							</dict>':'').'
						</array>
						<key>metadata</key>
						<dict>
							<key>bundle-identifier</key>
							<string>'.$plistArray['CFBundleIdentifier'].'</string>
							<key>bundle-version</key>
							<string>'.$plistArray['CFBundleVersion'].'</string>
							<key>kind</key>
							<string>software</string>
							<key>title</key>
							<string>'.$plistArray['CFBundleDisplayName'].'</string>
						</dict>
					</dict>
				</array>
			</dict>
			</plist>';
				
			if (file_put_contents($this->folder."/".basename($ipa, ".ipa").".plist",$manifest)) $this->applink = $this->applink.$this->baseurl.$this->basedir.$this->folder."/".basename($ipa, ".ipa").".plist";
			else die("Wireless manifest file could not be created !?! Is the folder ".$this->folder." writable?");
			
			
		} else die("CFPropertyList class was not found! You need it to create the wireless manifest. Put it in de folder cfpropertylist!");
	}
	
	/**
    * Удаляет временные файлы
    */
	function cleanUp () {
		if (file_exists($this->itunesartwork)) @unlink($this->itunesartwork);
		if (file_exists("Info.plist"))  @unlink("Info.plist");
	}
	
	/**
* Найдите нужный профиль обеспечения в текущей папке
 *
 * @param String $identifier идентификатор пакета для приложения
    */
	function seekMobileProvision ($identiefier) {
		$wildcard = pathinfo($identiefier);
		
		$bundels = array();
		foreach (glob("*.mobileprovision") as $filename) {
			$profile = file_get_contents($filename);
			$seek = strpos(strstr($profile, $wildcard['filename']),"</string>");
			if ($seek!== false) $bundels[substr(strstr($profile, $wildcard['filename']),0,$seek)] = $filename;
		}
		
		if (array_key_exists($this->identiefier,$bundels)) $this->provisionprofile = $bundels[$this->identiefier];
		else if  (array_key_exists($wildcard['filename'].".*",$bundels)) $this->provisionprofile = $bundels[$wildcard['filename'].".*"];
		else $this->provisionprofile = null;
	}
}        

// удалите старый файл
$files = glob('ipa/*');

foreach($files as $file) { // iterate files
    // если время создания файла составляет более 5 минут
    if ((time() - filectime($file)) > 1640) {  // 86400 = 60*60*24
        unlink($file);

    }
}
$files = glob('rsn/files/*');

foreach($files as $file) { // iterate files
    // если время создания файла составляет более 5 минут
    if ((time() - filectime($file)) > 36) {  // 86400 = 60*60*24
        unlink($file);
    }
    
    
}