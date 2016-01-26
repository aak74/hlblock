<?
CModule::includeModule('highloadblock');

use \Bitrix\Highloadblock as HL;
use \Bitrix\Main\Data\cache;

/**
 * @author Андрей Копылов aakopylov@mail.ru
 * new version at https://github.com/aak74/hlblock
 */
class CHLReference {
	const
		CACHE_PERIOD = 7200,
		CACHE_PATH = "hlblock/";

	private 
		$id = false,
		$item = false,
		$hlblock = false,
		$arCache = array(),
		$entity;

	public function __construct($name, $id = false) {
		// получаем id hlblock по его имени
		$objBlock = HL\HighloadBlockTable::getList(array(
			"filter" => array("NAME" => $name)
		));
		$blockEl = $objBlock->Fetch();
		$this->hlblock = $blockEl["ID"];

		$this->entity = HL\HighloadBlockTable::compileEntity($blockEl);
		$this->id = $id;
		$this->arCache = array(
			"path" => self::CACHE_PATH . $this->id . '/',
			"cachePeriod" => self::CACHE_PERIOD
		);

		return $this;
   	}
	
   	// Возвращает элемент в соответствии с переданным фильтром
	function getItem($filter = array()) {
		$result = $this->getList(array(), $filter);
		if (is_array($result)) {
			$result = current($result);
		} else {
			$result = false;
		}
		return $result;
	}

	/* Возвращает список элементов
	 * Запрос кэшируется
	 */
	function getList($order = array(), $filter = array()) {
		$arParams = array(
			'order' => $order,
			'filter' => $filter
		);
		$cache = $this->_createCacheInstance(md5( json_encode($arParams) ) );
		if ($this->_isCacheExists()) {
			$result = $cache->GetVars();
		} else {
			$entityDataClass = $this->entity->getDataClass();
			$res = $entityDataClass::getList($arParams);

			while ($el = $res->Fetch()) {
				$result[$el["ID"]] = $el;
			}
			$this->_saveCache(
				$cache,
				$result
			 );
		}

		return $result;
	}

	// Возвращает значение отдельного поля элемента
	public function getField($fieldName) {
		$result = false;
		if ($this->id) {
			if (!$this->item) {
			  $this->item = $this->getItem(array('ID' => $this->id));
			}
			$result = $this->item[$fieldName];
		}
		return $result;
	}

	// Добавляет элемент
	function add($params) {
		$entityDataClass = $this->entity->getDataClass();
	  
		$result = $entityDataClass::add($params);
		$id = $result->getId();
		// if($result->isSuccess())
		$this->_clearCache();
		return $id;
	}

	// Удаляет элемент
	function delete($id) {
		$entityDataClass = $this->entity->getDataClass();
	  
		$result = $entityDataClass::delete($id);
		$this->_clearCache();
		return $result->isSuccess();
	}

	// Обновляет элемент
	function update($id, $params) {
		$entityDataClass = $this->entity->getDataClass();
	  
		$result = $entityDataClass::update($id, $params);
		$this->_clearCache();
		return $id;
	}

	/* Добавляет данные или обновляет их.
	 * Ищет элемент в соответствии с фильтром. Если найден, то обновляет данные. 
	 * В противном случае добавляет элемент.
	 */
	function updateEx($filter, $params) {
		if ($item = $this->getItem($filter)) {
			$id = $item["ID"];
			$this->update($id, $params);
		} else {
			$id = $this->add($params);
		}
		return $id;
	}

	function getObjectName() {
		return "HLBLOCK_" . $this->hlblock;
	}

	function getFields() {
		global $USER_FIELD_MANAGER;
		return $USER_FIELD_MANAGER->GetUserFields($this->getObjectName());
	}

	function getFieldId($fieldName) {
		$fields = $this->getFields();
		return $fields[$fieldName]["ID"];
	}

	function getEnumValues($fieldId) {
		$obj = new CUserFieldEnum();
		$list = $obj->GetList(
			array(), 
			array("USER_FIELD_ID" => $fieldId)
		);
		while ($el = $list->Fetch()) {
			$result[$el["ID"]] = $el;
		}
		return $result;
	}

	/* Возвращаем метаданные HL блока*/
	function getMetaData() {
		// echo $this->hlblock;
		$dbHblock = HL\HighloadBlockTable::getList(array(
			'filter'=>array('ID' => $this->hlblock)
		));

		if ($res = $dbHblock->Fetch()) {
			$obj = CUserTypeEntity::GetList(array(), array("ENTITY_ID" => "HLBLOCK_" . $this->hlblock) );
			while($el = $obj->Fetch()) {
			}
		}

		return $result;
	}

	/* Создаем Instance кэша при установленном периоде кэширования и наличии в параметрах пути и ид кэша */
	private function _createCacheInstance($cacheId) {
		$this->arCache["exists"] = false;
		$this->arCache["id"] = $cacheId;
		if ( $this->arCache["cachePeriod"] > 0 ) {
			$cache = cache::createInstance();
			$this->arCache["exists"] = $cache->initCache( 
				$this->arCache["cachePeriod"], 
				$cacheId, 
				$this->arCache["path"] 
			);

			$result = $cache;
		} else {
			$result = false;
		}

		return $result;
	}

	/* сохраняем данные в кэш при установленном периоде кэширования */
	private function _saveCache($cache, $vars) {
		if ( ( $this->arCache["cachePeriod"] > 0 ) && $cache && $vars ) {
			$cache->startDataCache();
			$cache->endDataCache($vars);
			$result = true;
		} else {
			$result = false;
		}
		return $result;
	}
	
	private function _clearCache() {
		$cache = cache::createInstance();
		$cache::clearCache(false, $this->arCache["path"]);
	}	

	private function _isCacheExists($id) {
		if ( isset( $this->arCache[$id] )
			&& is_array( $this->arCache[$id] ) 
			&& isset( $this->arCache[$id]["exists"] )
			) {
			$result = $this->arCache[$id]["exists"];
		} else {
			$result = false;
		}

		return $result;
	}	

}
?>