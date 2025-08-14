<?php defined('BASEPATH') OR exit('No direct script access allowed');

class General {
	
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}
	
	public function clear_flashdata()
	{
		$this->CI->load->library('session');
		$data = array(
			"status_message" => "",
			"value_message" => ""
		);
		$this->CI->session->set_flashdata($data);
	}

	public function sanitize_input($input)
    {
    	$str = strip_tags($input); 
	    $str = preg_replace('/[\r\n\t ]+/', ' ', $str);
	    $str = preg_replace('/[\"\*\/\:\<\>\?\'\|]+/', ' ', $str);
	    $str = strtolower($str);
	    $str = html_entity_decode( $str, ENT_QUOTES, "utf-8" );
	    $str = htmlentities($str, ENT_QUOTES, "utf-8");
	    $str = preg_replace("/(&)([a-z])([a-z]+;)/i", '$2', $str);
	    $str = str_replace(' ', '-', $str);
	    $str = rawurlencode($str);
	    $str = str_replace('%', '-', $str);
	    return $str;
    }

    public function format($type,$value=""){
		$value = number_format($value,0,",",".");
		if ($type == 1){
			$value = "Rp ".$value.",-";
		}
    	return $value;
	}

	public function tanggalFormat($date,$type="0"){
		if ((date('Y-m-d H:i:s', strtotime($date)) === $date) || (date('d-m-Y H:i:s', strtotime($date)) === $date)){
			$tahun = substr($date,0,4);
			$bulan = substr($date,5,2);
			$tgl   = substr($date,8,2);
			$jam   = substr($date,11,5);
		 	if ($tgl < 10){$tgl = substr($tgl,1,1);}

			if ($type == "1"){
				$BulanIndo = array("Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember");
				$result = $tgl . " " . $BulanIndo[(int)$bulan-1] . " ". $tahun;
			}
			elseif($type == "2"){
				$BulanIndo = array("Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des");
				$result = $tgl . " " . $BulanIndo[(int)$bulan-1] . " ". substr($tahun,2,2);
			}
			else {
				$BulanIndo = array("Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des");
				$result = $tgl . " " . $BulanIndo[(int)$bulan-1] . " ". $tahun." ".$jam;
			}
			return($result);
		}
		else {
			return $date;
		}
	}
	
	public function change_date($delimiter,$separator,$date){
		$date = explode($delimiter,$date);
		$date = $date[2].$separator.$date[1].$separator.$date[0];
		return $date;
	}
	
	public function change_datetime($datetime){
		$datetime = explode(" ",$datetime);
		$date = explode("-",$datetime[0]);
		$date = $date[2]."-".$date[1]."-".$date[0];
		$time = $datetime[1];
		return $date." ".$time;
	}
	
	public function get_date($format=""){
		$dat_server = mktime(date("G"), date("i"), date("s"), date("n"), date("j"), date("Y"));
        $diff_gmt = substr(date("O",$dat_server),1,2);
        $dathif_gmt = 60 * 60 * $diff_gmt;
        if (substr(date("O",$dathif_gmt),0,1) == '+') {
            $dat_gmt = $dat_server - $dathif_gmt;
        } else {
            $dat_gmt = $dat_server + $dathif_gmt;
        }
        $dathif_id = 60 * 60 * 7;
		$dat_id = $dat_gmt + $dathif_id;
        $datetime = date("Y-m-d", $dat_id);
        if (!empty($format)){
        	$datetime = date("$format", $dat_id);
        }
        return $datetime; 
	}

	public function get_datetime($factor="",$value=0){
		$dat_server = mktime(date("G"), date("i"), date("s"), date("n"), date("j"), date("Y"));
        $diff_gmt = substr(date("O",$dat_server),1,2);
        $dathif_gmt = 60 * 60 * $diff_gmt;
        if (substr(date("O",$dathif_gmt),0,1) == '+') {
            $dat_gmt = $dat_server - $dathif_gmt;
        } else {
            $dat_gmt = $dat_server + $dathif_gmt;
        }
        $dathif_id = 60 * 60 * 7;
		if (!empty($factor)){
			if ($factor == "plus"){
				$dat_id = $dat_gmt + $dathif_id + $value;
			}
			if ($factor == "minus"){
				$dat_id = $dat_gmt + $dathif_id - $value;
			}
		}
		else {
			$dat_id = $dat_gmt + $dathif_id;
		}
        $datetime = date("Y-m-d H:i:s", $dat_id);
        return $datetime; 
	}
	
}

?>