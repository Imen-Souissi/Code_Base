<?php
namespace Application\Mask;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;

use Application\Model\MaskStrings;

class Masker implements ServiceLocatorAwareInterface {
    protected $sm;
    
    // if the string matches a word split with atleast 2 dashes, then we will consider this as a possible key
    const LICENSE_KEY_PATTERN = '/([a-zA-Z0-9]+\-[a-zA-Z0-9]+\-[a-zA-Z0-9\-]+)/';
    
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator) {
        $this->sm = $serviceLocator;
    }
    
    public function getServiceLocator() {
        return $this->sm;
    }
    
    protected function buildMaskHtml($mask_id, $mask) {
        return '<span class="mask" data-id="' . $mask_id . '">' . $mask . '</span>';
    }
    
    public function maskWithPatternToHtml($str, $pattern = self::LICENSE_KEY_PATTERN) {
        $maskstr = $str;
        $results = $this->maskWithPattern($str, $pattern);
        
        if($results) {
            foreach($results as $result) {
                list($mask_id, $mask, $full) = $result;
                
                $mask_html = $this->buildMaskHtml($mask_id, $mask);
                
                $maskstr = str_replace($full, $mask_html, $maskstr);
            }
        }
        
        return $maskstr;
    }
    
    public function maskWithPattern($str, $pattern = self::LICENSE_KEY_PATTERN) {
        $results = array();
        
        if(preg_match_all($pattern, $str, $matches, PREG_SET_ORDER)) {
            foreach($matches as $match) {
                list($mask_id, $mask, $full) = $this->mask($match[1]);
                
                if($mask_id) {
                    $results[] = array($mask_id, $mask, $full);
                }
            }
        }
        
        return $results;
    }
    
    public function maskToHtml($full) {
        list($mask_id, $mask) = $this->mask($full);
        
        return $this->buildMaskHtml($mask_id, $mask);
    }
    
    public function mask($full) {
        $mask_strings_mdl = MaskStrings::factory($this->getServiceLocator());
        
        $half = floor(strlen($full) / 2);
        
        if($half > 0) {
            $mask = substr($full, 0, $half) . '...';
        } else {
            $mask = $full;
        }
        
        // check if this mask string exists
        $mask_row = $mask_strings_mdl->get(array(
            'full' => $full,
            'mask' => $mask,
            'unmasker' => get_class($this)
        ));
        
        if($mask_row === false) {
            $mask_id = $mask_strings_mdl->insert(
                $full,
                $mask,
                get_class($this)
            );
        } else {
            $mask_id = $mask_row->id;
        }
        
        return array($mask_id, $mask, $full);
    }
    
    public function unmask($id, $mask_string = null) {
        if(!$mask_string) {
            $mask_strings_mdl = MaskStrings::factory($this->getServiceLocator());
            $mask_string = $mask_strings_mdl->get($id);
        }
        
        if($mask_string) {
            return $mask_string->full;
        }
        
        return false;
    }
}
?>