<?php 
$time_zone = str_replace("|","/",$_GET["time_zone"]);
date_default_timezone_set($time_zone);
require_once 'Mobile_Detect.php';
include 'GIFEncoder.class.php';
include 'php52-fix.php';

$campaign_id = $_GET["campaign_id"];
$campaign_config_file = "config_files/" . $campaign_id . "_config.php";
$campaign_config = jsonToArray($campaign_config_file);

$time_zone_date = new DateTime("now");
$time_zone_date_time = $time_zone_date->format('Y-m-d H:i:s');

$campaign_expiration_date = $campaign_config['campaign_expiration_date'];
$expiration_date = new DateTime($campaign_expiration_date);
$expiration_date_time = $expiration_date->format('Y-m-d H:i:s');
$time_left = $time_zone_date->diff($expiration_date);

$expires_soon_info = $campaign_config['expires_soon_info'];
$settings_type = determineSettings($time_zone_date,$expires_soon_info,$expiration_date,$campaign_expiration_date);

$detect = new Mobile_Detect;

$type = strtoupper($_GET["type"]);
if ($type == "MD") {
    $type = "mobile_detect";
    if ($detect->isMobile()) {
        $layout_type = "mobile";
    }
    else {
        $layout_type = "desktop";
    }
}
elseif ($type == "D") {
    $layout_type = "desktop";
}
else {
    $layout_type = "mobile";
}
$settings = $campaign_config[$layout_type][$settings_type];
$image_font = 'fonts/' . $campaign_config[$layout_type]['time_font'] . '.ttf';
$frames = array();
$delays = array();
$delay = 100;

$loop_image = generateLoopImage($time_zone_date,$settings);


for ($i = 0; $i <= 60; $i++) {
    $interval = date_diff($expiration_date, $time_zone_date);
    $settings_type = determineSettings($time_zone_date,$expires_soon_info,$expiration_date,$campaign_expiration_date);
    $settings = $campaign_config[$layout_type][$settings_type];
    $center_max_width_max_height = findCenterMaxWidthMaxHeight($image_font,$settings['time_font_size']);
    $center_max_width = $center_max_width_max_height[0];
    $center_max_height = $center_max_width_max_height[1];

    $loop_image = generateLoopImage($time_zone_date,$settings);

    $loop_image_days = twoCharacterString($interval->format("%a"));
    $loop_image_hours = twoCharacterString($interval->format("%H"));
    $loop_image_minutes = twoCharacterString($interval->format("%I"));
    $loop_image_seconds = twoCharacterString($interval->format("%S"));

    $loop_image = writeTimeToLoopImage($image_font,$settings,$loop_image_days,'days',$loop_image,$center_max_width,$center_max_height);
    $loop_image = writeTimeToLoopImage($image_font,$settings,$loop_image_hours,'hours',$loop_image,$center_max_width,$center_max_height);
    $loop_image = writeTimeToLoopImage($image_font,$settings,$loop_image_minutes,'minutes',$loop_image,$center_max_width,$center_max_height);
    $loop_image = writeTimeToLoopImage($image_font,$settings,$loop_image_seconds,'seconds',$loop_image,$center_max_width,$center_max_height);
    
    ob_start();
    imagegif($loop_image);
    $frames[] = ob_get_contents();
    $delays[] = $delay;
    $loops = 0;
    ob_end_clean();
    $time_zone_date->modify('+1 second');
}
$gif = new AnimatedGif($frames,$delays,$loops);
header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
header( 'Cache-Control: no-store, no-cache, must-revalidate' );
header( 'Cache-Control: post-check=0, pre-check=0', false );
header( 'Pragma: no-cache' );
header('Content-type:image/gif');
$gif->display();

function findCenterMaxWidthMaxHeight($font,$font_size) {
    $max_width = 0;
    $max_height = 0;
    for ($x = 0; $x < 10; $x++) {
        $text_dimensions = getTextDimensionsBasic($font,$font_size,$x);
        if ($text_dimensions[0] > $max_width) {
            $max_width = $text_dimensions[0];
        }
        if ($text_dimensions[1] > $max_height) {
            $max_height = $text_dimensions[1];
        }
    }
    return array($max_width,$max_height);
}

function writeTimeToLoopImage($image_font,$settings,$image_text,$time_type,$loop_image,$center_max_width,$center_max_height) {
    $character_one = substr($image_text,0,1);
    $character_two = substr($image_text,1,1);
    $json_position_settings = explode(",", $settings[$time_type . '_position']);
    $position_one_settings = getTextDimensionsBasic($image_font,$settings['time_font_size'],$character_one);
    
    
    $character_one_x_position = $json_position_settings[0] - $center_max_width + (($center_max_width - $position_one_settings[0]) / 2) - 1;
    $position_two_settings = getTextDimensionsBasic($image_font,$settings['time_font_size'],$character_two);
    $character_two_x_position = $json_position_settings[0] + 1;
    $y_position = $json_position_settings[1] + ($position_one_settings[1] / 2);
    imagettftext($loop_image,$settings['time_font_size'],0,$character_one_x_position,$y_position,$settings['time_font_color'],$image_font, $character_one);
    imagettftext($loop_image,$settings['time_font_size'],0,$character_two_x_position,$y_position,$settings['time_font_color'],$image_font, $character_two);
    return $loop_image;
}

function generateLoopImage($time_zone_date,$settings) {
    //echo $time_zone_date->format('Y-m-d H:i:s') . "   " . $settings_type . "<br>";
    if ($settings['background_type'] == "image") {
        $image_dimensions = getimagesize("images/" . $settings['background_value']);
        switch ($image_dimensions[2]) {
            case 1: 
                $im = imagecreatefromgif("images/" . $settings['background_value']); 
                break;
            case 2: 
                $im = imagecreatefromjpeg("images/" . $settings['background_value']);  
                break;
            case 3: 
                $im = imagecreatefrompng("images/" . $settings['background_value']);
                break;
        }
        $image_width = $image_dimensions[0];
        $image_height = $image_dimensions[1];
    }
    else {
        $image_width = $settings['image_width'];
        $image_height = $settings['image_height'];
        list($background_color_r, $background_color_g, $background_color_b) = getRGBfromHEX($settings['background_value']);
        $im = imagecreatetruecolor($image_width,$image_height);
        $im_color = imagecolorallocate($im, $background_color_r, $background_color_g, $background_color_b);
        imagefilledrectangle($im, 0, 0, $image_width, $image_height, $im_color);
    }
    $image = imagecreatetruecolor($image_width,$image_height);
    $transparent = imagecolorallocatealpha($image, 255, 255, 255, 0);
    imagefilledrectangle($image, 0, 0, $image_width, $image_height, $transparent);
    if ($settings['background_type'] != "transparent") {
        imagecopyresampled($image, $im, 0, 0, 0, 0, $image_width, $image_height, $image_width, $image_height);
    }
    imagesavealpha($image,true);
    return $image;
}

function twoCharacterString($text) {
    if (strlen($text) < 2) {
        $text = '0'.$text;
    }
    return $text;
}

function getTextDimensionsBasic($font,$font_size,$text) {
    $text_image_settings = imagettfbbox($font_size, 0, $font, $text);
    $width = $text_image_settings[4] - $text_image_settings[6];
    $height = $text_image_settings[3] - $text_image_settings[5];
    return array($width,$height);
}

function determineSettings($time_zone_date,$expires_soon_info,$expiration_date,$static_expiration_date) {
    if ($time_zone_date > $expiration_date) {
        return "expired";
    }
    else {
        if ($expires_soon_info['use_info']) {
            $expiring_type = $expires_soon_info['type'];
            $expiring_value = $expires_soon_info['value'];
            $function_expiration_date = new DateTime($static_expiration_date);
            if ($expiring_type == "hours") {
                $function_expiration_date->sub(new DateInterval('PT' . $expiring_value . 'H'));
            }
            elseif ($expiring_type == "minutes") {
                $function_expiration_date->sub(new DateInterval('PT' . $expiring_value . 'M'));
            }
            elseif ($expiring_type == "seconds") {
                $function_expiration_date->sub(new DateInterval('PT' . $expiring_value . 'S'));
            }
            else {
                $function_expiration_date->sub(new DateInterval('P' . $expiring_value . 'D'));
            }
            if ($time_zone_date > $function_expiration_date) {
                return "expires_soon";
            }
            else {
                return "default";
            }
        }
        else {
            return "default";
        }
    }
     
}

function pixelsToPoints($pixel_value) {
    return round($pixel_value * .75);
}

function jsonToArray($campaign_config_file) {
    $campaign_config_file = str_replace(".php",".json",$campaign_config_file);
    $json_string = file_get_contents($campaign_config_file);
    return json_decode($json_string, true);
}

function getRGBfromHEX($hex) {
    return array_map('hexdec', str_split($hex, 2));
}

/*echo "campaign: " . $campaign_id . "<br>";
echo "time_zone: " . $time_zone . "<br>";
echo "time_zone_date: " . $time_zone_date_time . "<br>";
echo "expiration_date: " . $expiration_date_time . "<br>";
echo $time_left->days.' days total<br>';
echo $time_left->y.' years<br>';
echo $time_left->m.' months<br>';
echo $time_left->d.' days<br>';
echo $time_left->h.' hours<br>';
echo $time_left->i.' minutes<br>';
echo $time_left->s.' seconds<br>';
echo "type: " . $type . "<br>";
echo "background: " . $settings["background_type"] . "<br>";
echo "background: " . $settings["background_value"] . "<br>";
echo "settings: [" . $layout_type . "][" . $settings_type . "]<br>";*/
?>