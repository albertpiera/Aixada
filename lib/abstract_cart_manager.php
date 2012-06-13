<?php

  /** 
   * @package Aixada
   */ 

require_once('FirePHPCore/lib/FirePHPCore/FirePHP.class.php');
ob_start(); // Starts FirePHP output buffering
$firephp = FirePHP::getInstance(true);

//ob_start(); // Starts FirePHP output buffering
require_once('exceptions.php');
require_once('local_config/config.php');
require_once('inc/database.php');

/**
 * This is the base class for rows of the tables.
 * @package Aixada
 * @subpackage Shop_and_Orders
 */

class abstract_cart_row {

    /**
	* @var int the unique id
	*/
    protected $_row_id = 0;

    /**
	* @var date the date when the cart is processed
	*/
    protected $_date = 0;

    /**
	* @var int the unique id of the uf buying or ordering
	*/
    protected $_uf_id = 0;

    /**
	* @var int the unique id of the product that the row stores
	*/
    protected $_product_id = 0;

    /**
	* @var float the quantity of the product
	*/
    protected $_quantity = 0;
    
    
    /**
     * Enter description here ...
     * @var unknown_type
     */
    protected $_cart_id = 0; 
    
    /**
     * The constructor takes the id of the containing cart, of the
     * product, the quantity and the price
     */
    public function __construct($date, $uf_id, $product_id, $quantity, $cart_id)
    {
        $this->_date = $date;
        $this->_uf_id = $uf_id;
        $this->_product_id = $product_id;
        $this->_quantity = $quantity;
        $this->_cart_id = $cart_id; 
    }

    


    /**
     * return the product id
     */
    public function get_product_id()
    {
        return $this->_product_id;
    }

    /**
     * return the quantity
     */
    public function get_quantity()
    {
        return $this->_quantity;
    }

    public function commit_string() {}
}



/**
 * The common part for all classes that manage a shopping
 * cart. Derived classes must  overwrite
 * @see _make_rows , @see _commit_cart
 *
 * @package Aixada
 * @subpackage Shop_and_Orders
 */
class abstract_cart_manager {
  
    /**
     * @var int stores the UF buying
     */
    protected $_uf_id;

    /**
     * @var date stores the current date 
     */
    protected $_date;
    
    /**
     * @var int the cart id
     */
    protected $_cart_id; 
    
    /**
     * @var array_of_rows 
     */
    protected $_rows;

    
    /**
     * @var boolean Has the cart been successfully committed?
     */
    protected $_commit_succeeded = false;

    /**
     * @var string the name of the database table that stores the items
     */
    protected $_item_table_name = '';

    /**
     * @var string this is 'shop', 'order'
     */
    protected $_id_string = '';

    /**
     * mysql commit prefix 
     * @var string
     */
    protected $_commit_rows_prefix = '';

 
       
    public function __construct($uf_id, $date=0)
    {
        if (!$uf_id) {
            throw new Exception('abstract_cart_manager::_construct: Need to specify uf_id');
            exit;
        }
        $this->_uf_id = $uf_id;
        $this->_date = $date;
        $this->_cart_id = 0;
        $this->_rows = array();
        $this->_item_table_name = 'aixada_' . $this->_id_string . '_item';
    }


    public function commit($arrQuant, $arrProdId, $arrIva, $arrRevTax, $arrOrderItemId, $cart_id, $arrPreOrder) 
    {
		// pre-empt double validations
		if (count($arrQuant)==0) 
		    return;
		    
        $this->_rows = array();
        
		// are the input array sizes consistent?        
        if ( count($arrQuant)!=count($arrProdId) )
            throw new Exception($this->_id_string . "_cart_manager::commit: mismatched array sizes: " . $arrQuant . ', ' . $arrProdId);
            
            
        	// now proceed to commit
        $db = DBWrap::get_instance();
        try {
            $db->Execute('START TRANSACTION');
            $this->_make_rows($arrQuant, $arrProdId, $arrIva, $arrRevTax, $arrOrderItemId, $cart_id, $arrPreOrder);
            $this->_check_rows();
            $this->_delete_rows();
            $this->_commit_rows();
            $this->_postprocessing($arrQuant, $arrProdId, $arrIva, $arrRevTax, $arrOrderItemId, $cart_id, $arrPreOrder);
            $db->Execute('COMMIT');
            clear_cache($this->_item_table_name);
        }
        catch (Exception $e) {
            global $firephp;
            $firephp->log($e);
            $this->_commit_succeeded = false;
            $db->Execute('ROLLBACK');
            throw($e);
        }
        $this->_commit_succeeded = true;    
        
        return $this->_cart_id; 

    }



    /**
     * abstract function to make the row classes
     */ 
    protected function _make_rows($arrQuant, $arrProdId, $arrIva, $arrRevTax, $arrOrderItemId, $cart_id, $arrPreOrder)
    {
    }

    /**
     * abstract function to check the rows that were added
     */ 
    protected function _check_rows()
    {
    }

    /**
     * abstract function to delete the rows of a cart from an *_item table
     */ 
    protected function _delete_rows()
    {
        $db = DBWrap::get_instance();
        $db->Execute('delete from ' 
                     . $this->_item_table_name 
                     . ' where uf_id=:1q and date_for_'
                     . $this->_id_string 
                     . '=:2q', $this->_uf_id, $this->_date);
    }
  
    /**
     * Commits the rows of the order to the database. 
     */
    protected function _commit_rows()
    {
        if (count($this->_rows) == 0) 
            return;
        
        $commitSQL = $this->_commit_rows_prefix;
        foreach ($this->_rows as $index => $row) {
            $commitSQL .= $row->commit_string() . ',';
        }
        $commitSQL = rtrim($commitSQL, ',') . ';';
        
        global $firephp;
        $firephp->log($commitSQL, "commitSQL");
    		
        
        DBWrap::get_instance()->Execute($commitSQL);
    }

    /**
     * abstract function for postprocessing
     */ 
    protected function _postprocessing()
    {
    }



    /**
     * Convert foreign key cache to XML
     * @param array $cache a foreign key cache
     * @param string $selected_option the option actually selected 
     * @return string the corresponding XML string
     */
    protected function _key_cache_to_XML (array $cache, string $selected_option)
    {
        $strXML = '<choices>';
        foreach($cache as $value => $field) 
            $strXML .= "<c>$field</c>"; 
        $strXML .= "<s>$selected_option</s>";
        $strXML .= '</choices>';
        return $strXML;
    }

}



?>