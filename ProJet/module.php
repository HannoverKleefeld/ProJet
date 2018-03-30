<?php
const 
	RGB_RED = 0,
	RGB_GREEN=1,
	RGB_BLUE=2;

class rgb {
	public static function fromInt(int $Color){
		list($rgb[RGB_RED], $rgb[RGB_GREEN], $rgb[RGB_BLUE]) = sscanf(substr('000000'.dechex($Color),-6), "%02x%02x%02x");
		return $rgb;
	}
	public static function fromHex(string $HexColor){
		if($HexColor && $HexColor[0]=='#')$HexColor=substr($HexColor,1);
		list($rgb[RGB_RED], $rgb[RGB_GREEN], $rgb[RGB_BLUE]) = sscanf(substr('000000'.$HexColor,-6), "%02x%02x%02x");
		return $rgb;
	}
	public static function toHex(int $Red, int $Green , int $Blue){
		return sprintf('%02x%02x%02x',$Red,$Green,$Blue);	
	}
	public static function toHexA(array $rgb){
		return sprintf('%02x%02x%02x',$rgb[RGB_RED], $rgb[RGB_GREEN], $rgb[RGB_BLUE]);	
	}
	public static function toInt(int $Red, int $Green , int $Blue){
		return hexdec(static::ToHex($Red, $Green, $Blue));		
	}
	public static function toIntA(array $rgb){
		return hexdec(static::ToHexA($rgb));		
	}
	public static function setLevel(array &$rgb, $NewLevel, $OldLevel=null){
		if(is_null($OldLevel))$OldLevel=round(max($rgb)/2.55);
		if($OldLevel==$NewLevel)return false;
		foreach($rgb as &$v){
			$v=round(($v/$OldLevel)*$NewLevel);
			if($v>255)$v=255;elseif($v>0)$v--;
		}	
		return true;
	}	
}

class ProJet extends IPSModule {
	private $mode = '';
	function Create(){
		parent::Create();
 		$this->registerPropertyInteger('DeviceID',144);
 		$this->registerPropertyInteger('Mode',0);
 		$this->registerPropertyInteger('DimSpeed',0);
	}
	function ApplyChanges(){
		parent::ApplyChanges();
		$this->ConnectParent("{995946C3-7995-48A5-86E1-6FB16C3A0F8A}");
		$this->registerVariableBoolean('STATE',$this->Translate('State'),'~Switch',0);
 		$this->registerVariableInteger('LEVEL',$this->Translate('Level'),'~Intensity.100',1);
		$this->registerVariableInteger('COLOR',$this->Translate('Color'),'~HexColor',2);
 		$this->registerVariableInteger('WHITE',$this->Translate('White'),'~Intensity.255',9);
 		$this->enableAction(['STATE','LEVEL','COLOR','WHITE']);
 		if($this->ReadPropertyInteger('Mode')==0){ // Color Mode
			@$this->disableAction(['RED','GREEN','BLUE']);
 			@$this->unregisterVariable('RED');
 			@$this->unregisterVariable('GREEN');
 			@$this->unregisterVariable('BLUE');
 		}else{ // RGB Mode
 			$RGB=IntToRgb($this->getValue('COLOR'));
 			if($id=$this->registerVariableInteger('RED',$this->Translate('Red'),'~Intensity.255',2))SetValue($id, $RGB['r']);
 			if($id=$this->registerVariableInteger('GREEN',$this->Translate('Green'),'~Intensity.255',3))SetValue($id, $RGB['g']);
 			if($id=$this->registerVariableInteger('BLUE',$this->Translate('Blue'),'~Intensity.255',4))SetValue($id, $RGB['b']);
			$this->enableAction(['RED','GREEN','BLUE']);
 		}
 	}
	function RequestAction($ident, $value){
		switch($ident){
 			case 'STATE': $this->SetState($value); break;
   			case 'LEVEL': $this->SetLevel($value); break;
 			case 'COLOR': $this->SetColor($value); break;
   			case 'WHITE': $this->SetWhite($value); break;			   			
 			case 'RED'	: $this->SetRed  ($value); break;
   			case 'GREEN': $this->SetGreen($value); break;
   			case 'BLUE'	: $this->SetBlue ($value); break;
		}
	}
 	function ReceiveData($JSONString){
 		$this->SendDebug(__FUNCTION__,'Message::'.$JSONString,0);
 	}	 
	protected function enableAction($action){
		if(!is_array($action))
			parent::enableAction($action);	
		else foreach($action as $a)parent::enableAction($a);
	}
	protected function disableAction($action){
		if(!is_array($action))
			parent::disableAction($action);
		else foreach($action as $a)parent::disableAction($a);
	}

 	public function SetState(bool $StateOn){
 		if($this->getValue('STATE')===$StateOn)return true;
 		return $StateOn? $this->_upState():$this->_downState();
 	}
	
 	public function SetColor(int  $Color){
  		if(($oldColor=$this->GetValue('COLOR'))==$Color) return;
  		if($Color==0 && $oldColor)$this->SetBuffer('OnColor',$oldColor);
  		$rgb=rgb::fromInt($Color);
 		if($dimSpeed=$this->ReadPropertyInteger('DimSpeed'))
			return $this->DimRGBW($rgb[RGB_RED], $dimSpeed, $rgb[RGB_GREEN], $dimSpeed, $rgb[RGB_BLUE], $dimSpeed, intval($this->getValue('WHITE')), $dimSpeed);	
		return $this->SetRGBW($rgb[RGB_RED], $rgb[RGB_GREEN], $rgb[RGB_BLUE], intval($this->getValue('WHITE')));	
  	}
 	public function SetLevel(int $DimLevel){
 		if($DimLevel>100)$DimLevel=100;elseif($DimLevel<0)$DimLevel=0;
  		if($this->GetValue('LEVEL')==$DimLevel) return true;
 		$rgb=rgb::fromInt($color=$this->getValue('COLOR'));
 		if(!rgb::setLevel($rgb, $DimLevel))return true;
 		if($color)$this->SetBuffer('OnColor', $color);
 		if($dimSpeed=$this->ReadPropertyInteger('DimSpeed'))
			return $this->DimRGBW($rgb[RGB_RED], $dimSpeed, $rgb[RGB_GREEN], $dimSpeed, $rgb[RGB_BLUE], $dimSpeed, intval($this->getValue('WHITE')), $dimSpeed);	
		return $this->SetRGBW($rgb[RGB_RED], $rgb[RGB_GREEN], $rgb[RGB_BLUE], intval($this->getValue('WHITE')));	
 	}
 	
 	public function SetRGBW(int $R, int $G, int $B, int $W){
		if($ok=$this->_forwardData(['P',$R,$G,$B,$W]))	{
			$this->_updateByColor($R, $G, $B);
		}
		return (bool)$ok;
	}
 	public function DimRGBW(int $R, int $RZeit, int $G, int $GZeit, int $B, int $BZeit, int $W, int $WZeit){
		$toParam=function($v,$t){
			return $t>0?hexdec(sprintf('%02x%02x',$t,$v)):$v;
		};		
		$data=['P',$toParam($R,$RZeit),$toParam($G,$GZeit),$toParam($B,$BZeit),$toParam($W,$WZeit)];
 		if($ok=$this->_forwardData($data)){
			$this->_updateByColor($R, $G, $B);
 		}
 		return (bool)$ok;
	}
	public function DimUp(){
		return (($level=$this->getValue('LEVEL'))<100) ? $this->SetLevel($level+5):true;
 	}
 	public function DimDown(){
 		return (($level=$this->getValue('LEVEL'))>0) ? $this->SetLevel($level-5):true;
 	}
	public function RunProgram(int $Programm){
		if($ok=$this->_forwardData(['F',$Programm])){
			
		}
		return (bool)$ok;
	}
 	private function _updateByColor($r,$g,$b){
 		$this->setValue('COLOR', rgb::toInt($r, $g, $b));
 		$this->setValue('LEVEL', $level=round(max($r,$g,$b)/2.55));
 		$this->SetValue('STATE', $level!=0);
 	}
	private function _downState(){
		$this->SetBuffer('OnColor',$this->getValue('COLOR'));
		$this->SetRGBW(0, 0, 0, 0);
	}
	private function _upState(){
		$rgb=($onColor=intval($this->GetBuffer('OnColor')))?rgb::fromInt($onColor):[RGB_RED=>128,RGB_GREEN=>128,RGB_BLUE=>128];
		if($dimSpeed=$this->ReadPropertyInteger('DimSpeed'))
			$this->DimRGBW($rgb[RGB_RED], $dimSpeed, $rgb[RGB_GREEN], $dimSpeed, $rgb[RGB_BLUE], $dimSpeed, intval($this->getValue('WHITE')), $dimSpeed);			
		else 
			$this->SetRGBW($rgb[RGB_RED], $rgb[RGB_GREEN], $rgb[RGB_BLUE], intval($this->getValue('WHITE')));
	}
	
	private function _forwardData(array $value){
		$this->SendDebug(__FUNCTION__,'Send:'.var_export($value,true),0);
		$data['DataID']="{9DD17B0B-030F-4849-8BFF-88EB4BB414BA}";
		$data['Data']=$this->ReadPropertyInteger('DeviceID').','.implode(',',$value);
		$data=json_encode($data);
		$this->SendDebug(__FUNCTION__,'Send:'.$data,0);
		if(!$value=@$this->SendDataToParent($data))
			IPS_LogMessage(__CLASS__,'Error sending data to Device');
		else	
			$this->SendDebug(__FUNCTION__,'Return:'.var_export($value,true),0);
		return $value;		
	}
	
	private function setValue(string $ident, $value, $force=false){
		if( ($id=@$this->GetIDForIdent($ident)) && ($force || GetValue($id)!=$value))return SetValue($id,$value);
		return ($id>0);
	}
	private function getValue(string $ident){
		return ($id=@$this->GetIDForIdent($ident))?GetValue($id):null;
	}
 	
	
}
?>