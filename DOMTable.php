<?php

/*
	
	Project-Name: DOMTable
	
	Version: 1.0
	
	Author-Name: Uchenna Ajah
	
	Author-Slug: Ucscode
	
	Founded: Dec 20, 2022;
	
	Github-Profile: https://github.com/ucscode
	
	Author-Website: https://ucscode.me
	
	Description: A PHP Library that uses DOMDocument & Nodes to create custom HTML table
	
	Disclaimer: 
		This library only creates "Table" Element and has no stylesheet. 
		The CSS of the table element should be created by the programmer utilizing this library
	
	Help: To make custom features, consider extending the DOMTable class.
		
		E.G:
			
			class customDOMTable extends DOMTable { }
		
*/

class DOMTable {
	
	protected $doc;
	protected $table;
	protected $container;
	
	public $rows_per_page = 10;
	public $page = 1;
	
	protected $columns = [];
	protected $data;
	protected $total_rows;
	protected $max_pages;
	
	protected $appliance = array();
	
	/*
		[ DOMTable __constructor() ]
	*/
	
	public function __construct( ?string $tablename = null ) {
		
		// table name as element id;
		if( empty($tablename) ) $tablename = uniqid();
		
		//libxml_use_internal_errors(true);
		
		// Create a PHP DomDocument;
		$this->doc = new DOMDocument('1.0', 'utf-8');
		
		$this->doc->preserveWhiteSpace = false;
		$this->doc->formatOutput = true;
		
		// Create a Table Element;
		$HTML_TABLE = "
			<div class='dt-container'>
				<!-- a good spot add features like search box, checkbox options etc -->
				<div class='table-responsive'>
					<table class='table' id='dt-{$tablename}'>
						<thead/>
						<tbody/>
						<tfoot/>
					</table>
				</div>
				<!-- a good spot to add features like nav button, footer label etc -->
			</div>
		";
		
		// Load The Table;
		$this->doc->loadHTML($HTML_TABLE);
		$this->container = $this->doc->getElementsByTagName('div')->item(0);
		$this->table = $this->container->getElementsByTagName('table')->item(0);
		
	}
	
	public function __get($name) {
		if( property_exists($this, $name) ) return $this->{$name};
	}
	
	/*
		[ Create Table Columns ] 
	*/
	
	public function columns( array $columns, bool $tfoot = false ) {
		$this->columns[0] = [];
		foreach( $columns as $key => $value ) {
			if( is_numeric($key) ) $key = $value;
			$this->columns[0][$key] = $value;
		}
		$this->columns[1] = $tfoot;
	}
	
	
	/*
		[ Set Table Data ] 
	*/
	
	public function data( $data ) {
		if( !($data instanceof MYSQLI_RESULT ) && !is_array($data) )
			throw new Exception( __CLASS__ . "::" . __FUNCTION__ . "() argument must be an Array or an instance of Mysqli_Result" );
		$this->data = $data;
	}
	
	
	/*
		[ Process data ] 
	*/
	
	private function init_data($func) {
		
		if( !is_numeric($this->page) )
			throw new exception( __CLASS__ . "::\$page is not a valid interger [number]" );

		else if( !is_numeric($this->rows_per_page) )
			throw new exception( __CLASS__ . "::\$rows_per_page is not a valid interger [number]" );
		
		$begin = ((int)$this->page - 1) * (int)$this->rows_per_page;
		
		// process for data as Array;
		
		if( is_array($this->data) ) {
			$this->total_rows = count($this->data);
			$result = array_slice($this->data, $begin, (int)$this->rows_per_page );
			foreach( $result as $key => $data ) {
				$result[$key] = $this->modify_data( $data, $func );
			}
		} else {
			$this->total_rows = $this->data->num_rows;
			$result = array();
			$this->data->data_seek($begin);
			while( $data = $this->data->fetch_assoc() ) {
				if( count($result) == (int)$this->rows_per_page ) break;
				$result[] = $this->modify_data( $data, $func );
			};
		};
		
		$this->max_pages = ceil( $this->total_rows / (int)$this->rows_per_page );
		
		return $result;
		
	}
	
	private function modify_data( $data, $func ) {
		$missing_columns = array_diff( array_keys($this->columns[0]), array_keys($data) );
		if( !empty($missing_columns) ) {
			foreach( $missing_columns as $key ) $data[$key] = null;
		};
		$new_data = !$func ? $data : ($func($data) ?? $data);
		if( !is_array($new_data) ) $new_data = $data;
		return $new_data;
	}
	
	
	protected function extend_tbody( array $result ) {
		foreach( $result as $data ) {
			$undefined_key = array_diff( array_keys($data), array_keys($this->columns[0]) );
			if( !empty($undefined_key) ) {
				foreach( $undefined_key as $key ) unset($data[$key]);
			};
			$data = array_merge( $this->columns[0], $data );
			$this->create_row( array_values($data) );
		};
		return $this->table->getElementsByTagName('tbody')->item(0);
	}
	
	/*
		[ Complain if no result found ]
	*/
	
	protected function complain() {
		// Create DIV;
		$div = $this->doc->createElement('div');
		$div->setAttribute('class', 'dt-empty');
		# $div->setAttribute('align', 'center');
		// Create SPAN & appendTo DIV;
		$span = $this->doc->createElement('span');
		$div->appendChild($span);
		// Create TEXT & appendTo SPAN;
		$textNode = $this->doc->createTextNode('No result found');
		$span->appendChild($textNode);
		$this->container->appendChild($div);
	}
	
	
	/*
		[ 
			- Create <tr/> Element 
			- Append it to:
			- <thead>, <tbody/> or <tfoot/> 
		]
	*/
	
	protected function create_row( $columns, $type = 'td', $appendTo = 'tbody' ) {
		$approved = !empty(array_filter($columns, function($value) {
			return !is_null($value);
		}));
		if( $approved ) {
			# create a <tr/> element;
			$tr = $this->doc->createElement('tr');
			foreach( $columns as $value ) {
				$tx = $this->doc->createElement( $type );
				$this->innerHTML( $tx, $value );
				$tr->appendChild($tx);
			};
			$this->table->getElementsByTagName( $appendTo )->item(0)->appendChild( $tr );
		};
		return $approved;
	}
	
	
	/*
		[ GET OR SET innerHTML ]
	*/
	
	public function innerHTML( &$el, ?string $innerHTML = null ) {
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = true;
		$innerHTML = preg_replace("/&(?!\S+;)/", "&amp;", $innerHTML);
		$dom->loadHTML( "<div>{$innerHTML}</div>" );
		$div = $this->doc->importNode( $dom->getElementsByTagName('body')->item(0)->firstChild, true );
		while( $el->firstChild ) $el->removeChild( $el->firstChild );
		while( $div->firstChild ) $el->appendChild( $div->firstChild );
	}
	
	/*
		[
			- Display the HTML Table: OR
			- Return HTML Table as string
		]
	*/
	
	public function prepare( ?callable $func = null, bool $print = false ) {
		
		// create a new row for <thead/>
		$this->create_row( array_values($this->columns[0]), 'th', 'thead' );
		
		// prepare the <tr/> data passed by user : incase of modifications
		$result = $this->init_data($func);
		
		// extend data in <tbody/>
		$tbody = $this->extend_tbody( $result );
		
		if( empty($this->columns[1]) || !$tbody->hasChildNodes() ) {
			$this->table->removeChild( $this->table->getElementsByTagName('tfoot')->item(0) );
		} else {
			// create a new row for <tfoot/>: if applicable;
			$this->create_row( array_values($this->columns[0]), 'th', 'tfoot' );
		};
		
		// add data to <tbody/>
		if( !$tbody->hasChildNodes() ) $this->complain();
		
		$table = $this->doc->saveHTML( $this->container );
		return print_r( $table, !$print );
		
	}
	
	
}