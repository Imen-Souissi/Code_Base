<?php
namespace Application\Controller\Script;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ConsoleModel;
use Zend\Log\Logger;
use Zend\Json\Json;

class DocumentationController extends AbstractActionController {
    public function buildAction() {
        $sm = $this->getServiceLocator();
        $app_config = $sm->get('Application\Config');
        $config = $sm->get('Config');
		$logger = $sm->get('console_logger');
		
		$result = new ConsoleModel();
        
        $date = $this->params()->fromRoute('date', date('m/d/Y'));
        $version = $this->params()->fromRoute('version', $app_config['version']);
        $cleanup = $this->params()->fromRoute('cleanup');
		$build_pdf = $this->params()->fromRoute('build-pdf');
		
		$logger->log(Logger::INFO, "preparing to generate documentation for date {$date} and version {$version}");
        $cwd = getcwd();
        
        // make a copy of the documentation directory
        $src_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'documentation_' . md5(time() . rand());
        mkdir($src_dir, 0777, true);
		
        $cmd = "cp -R {$cwd}" . DIRECTORY_SEPARATOR . "documentation" . DIRECTORY_SEPARATOR . "* {$src_dir}" . DIRECTORY_SEPARATOR;
        exec($cmd, $output, $return);
        
        if($return != 0) {
            $logger->log(Logger::ERR, "unable to setup source documentation directory");
            $result->setErrorLevel(1);
            return $result;
        }
        
        unset($output);
        unset($return);
        
        $logger->log(Logger::INFO, "finish setting up source documentation directory");
        
		$contents = array();
		
		if(file_exists('documentation/contents.json')) {
			$contents = file_get_contents('documentation/contents.json');
			try {
				$contents = Json::decode($contents, Json::TYPE_ARRAY);
			} catch(\Exception $e) {
				// do nothing
			}
		}
		
        // generate the index.txt and contents.json
        $components = array();
		$documentations = array();
		
		// load the documentation configurations
		if(!empty($config['documentation']) && is_array($config['documentation'])) {
			foreach($config['documentation'] as $doc_cfg_file) {
				if(file_exists($doc_cfg_file)) {
					$doc_cfg = include_once($doc_cfg_file);
					if(!empty($doc_cfg)) {
						$documentations = array_merge_recursive($documentations, $doc_cfg);
					}
				}
			}
		}
		
        if(!empty($documentations) && is_array($documentations)) {
			usort($documentations, function($a, $b) {
				return strcmp($a['name'], $b['name']);
			});
			
            foreach($documentations as $component => $options) {
				if($this->buildComponent($component, $options, 0, $src_dir, $components, $contents) === false) {
					$result->setErrorLevel(1);
                    return $result;
				}
            }
        }
        
		$logger->log(Logger::INFO, "building index.txt");
		
        $index_txt = $src_dir . DIRECTORY_SEPARATOR . 'index.txt';
		$this->normalizeFileContent($index_txt, array(
			'content' => implode("\r\n", $components)
		));
		
        echo "\n" . file_get_contents($index_txt) . "\n";
		
		$logger->log(Logger::INFO, "building contents.json");
		$content_json = $src_dir . DIRECTORY_SEPARATOR . 'contents.json';
		file_put_contents($content_json, Json::prettyPrint(Json::encode($contents)));		
		
		if($build_pdf) {
			// now check if wkhtmltopdf is installed, if it is, we should build the pdf version as well
			$cmd = "which wkhtmltopdf";
			exec($cmd, $output, $return);
			
			list($wkhtmltopdf_bin) = $output;
			
			unset($output);
			unset($return);
			
			if(file_exists($wkhtmltopdf_bin)) {
				// build the pdf documentations
				$logger->log(Logger::INFO, "building pdf documentations");
				$cmd = "docgen run -i {$src_dir} -s \"{$version}\" -R \"{$date}\" -t -o $cwd/public/documentations -v -d 5000 -p";
				exec($cmd, $output, $return);
				
				if($return != 0) {
					$logger->log(Logger::ERR, "unable to generate documentations");
					$logger->log(Logger::ERR, implode("\n", $output));
					$result->setErrorLevel(1);
					return $result;
				} else if(file_exists("{$cwd}/public/documentations/.pdf")) {
					// move the pdf file which is named .pdf to user-guide.pdf
					copy("{$cwd}/public/documentations/.pdf", "{$cwd}/public/documentations/user-guide.pdf");
					unlink("{$cwd}/public/documentations/.pdf");
				}
				
				unset($output);
				unset($return);
			}
		} else {
			// build the html documentations
			$logger->log(Logger::INFO, "building html documentations");
			$cmd = "docgen run -i {$src_dir} -s \"{$version}\" -R \"{$date}\" -t -o $cwd/public/documentations";
			exec($cmd, $output, $return);
			
			if($return != 0) {
				$logger->log(Logger::ERR, "unable to generate documentations");
				$logger->log(Logger::ERR, implode("\n", $output));
				$result->setErrorLevel(1);
				return $result;
			}
			
			unset($output);
			unset($return);
		}
		
		// replace the coloring
		$cmd = "sed -i 's/#385691/#393939/g' {$cwd}/public/documentations/require/webknife/framework.min.css";
		exec($cmd, $output, $return);
		 
		if($return != 0) {
            $logger->log(Logger::ERR, "unable to replace colors in framework.min.css");
            $result->setErrorLevel(1);
            return $result;
        }
        
		unset($output);
		unset($return);
		
		// replace the coloring
		$cmd = "sed -i 's/#385691/#393939/g' {$cwd}/public/documentations/ownership.html";
		exec($cmd, $output, $return);
		
		if($return != 0) {
            $logger->log(Logger::ERR, "unable to replace colors in ownership.html");
            $result->setErrorLevel(1);
            return $result;
        }
        
		unset($output);
		unset($return);
		
		// put a .htaccess file in the documentations directory
		$htaccess = "Options +FollowSymLinks\n";
		$htaccess .= "Options -Indexes\n";
		$htaccess .= "\n";
		$htaccess .= "RewriteEngine Off\n";
		
		file_put_contents("{$cwd}/public/documentations/.htaccess", $htaccess);
		
		if($cleanup) {
			// remove the src_dir we have clone
			$cmd = "rm -rf {$src_dir}";
			exec($cmd, $output, $return);
			
			if($return != 0) {
				$logger->log(Logger::ERR, "unable to cleanup cloned source dir");
				$result->setErrorLevel(1);
				return $result;
			} else {
				$logger->log(Logger::INFO, "successfully cleanup cloned source dir");
			}
		}
		
        $logger->log(Logger::INFO, "successfully generated documentations");
        return $result;
    }
	
	protected function buildComponent($component, $options, $indent = 0, $src_dir, &$components, &$contents) {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('console_logger');
		
		$component_label = null;
		$name = ($options['name']) ? $options['name'] : ucfirst($component);
		$file = ($options['file']) ? $options['file'] : "";
		$assets_dir = ($options['assets']) ? $options['assets'] : null;
		
		if(is_file($file)) {
			$component_rel_file = $this->getComponentRelFile($file);					
			$component_rel_htmlfile = str_replace('.txt', '.html', $component_rel_file);
			
			if($indent == 0) {
				$components[] = $component_label = "## [{$name}](./{$component_rel_htmlfile})";
				$contents[0]['pages'][] = array(
					'title' => $name,
					'source' => $component_rel_file
				);
			} else {
				$components[] = $component_label = str_repeat("\t", $indent - 1) . "- [{$name}](./{$component_rel_htmlfile})";
				$contents[1]['pages'][] = array(
					'title' => $name,
					'source' => $component_rel_file
				);
			}
			
			$component_file = $src_dir . DIRECTORY_SEPARATOR . $component_rel_file;
			
			if(!copy($file, $component_file)) {
				$logger->log(Logger::ERR, "unable to copy file for component {$component}");
				return false;
			}
			
			$logger->log(Logger::INFO, "copied file for component {$component}");
		} else {
			$logger->log(Logger::INFO, "no file available for component {$component}");
			
			if($indent == 0) {
				$components[] = $component_label = "## {$name}";
			} else {
				$components[] = $component_label = str_repeat("\t", $indent - 1) . "- {$name}";
			}
		}
		
		if(is_dir($assets_dir)) {
			// we will need to read and copy everything within the assets directory over to the destination directory
			$assets_dir_name = basename($assets_dir);					
			$src_assets_dir = $src_dir . DIRECTORY_SEPARATOR . $assets_dir_name;					
			
			$this->copyDirectory($assets_dir, $src_assets_dir);
		}
		
		$all_subcomponents = array();
		
		if(!empty($options['components']) && is_array($options['components'])) {
			foreach($options['components'] as $subcomponent => $options) {
				$placeholder = (empty($options['placeholder'])) ? 'content' : $options['placeholder'];
				$subdata = $this->buildComponent($subcomponent, $options, $indent + 1, $src_dir, $components, $contents);
				
				if($subdata === false) {
					return false;
				}
				
				if(empty($all_subcomponents[$placeholder])) {
					$all_subcomponents[$placeholder] = array();
				}
				
				$all_subcomponents[$placeholder][] = $subdata['label'];
				if(!empty($subdata['sublabels'])) {
					foreach($subdata['sublabels'] as $label) {
						$all_subcomponents[$placeholder][] = $label;
					}
				}
			}
		}
		
		$data = array(
			'label' => $component_label,
			'sublabels' => array()
		);
		
		if(file_exists($component_file)) {
			$replacements = array();
			
			foreach($all_subcomponents as $placeholder => $links) {
				// if the indentation is bigger than 0, we will remove the first \t so that the subpages displays the bullet point correctly and not as a coded block
				if($indent > 0) {
					$replacements[$placeholder] = implode("\r\n", array_map(function($link) {
						return preg_replace("/^\t/", "", $link);
					}, $links));
				} else {
					$replacements[$placeholder] = implode("\r\n", $links);
				}
				
				foreach($links as $link) {
					$data['sublabels'][] = $link;
				}
			}
			
			$this->normalizeFileContent($component_file, $replacements);
		} else {
			foreach($all_subcomponents as $placeholder => $links) {
				foreach($links as $link) {
					$data['sublabels'][] = $link;
				}
			}
		}
		
		return $data;
	}
	
	protected function getComponentFile($src_dir, $file) {
		$src_dir = rtrim($src_dir, DIRECTORY_SEPARATOR);
		$subfile = $this->getComponentRelFile($file);
		
		return $src_dir . DIRECTORY_SEPARATOR . $subfile;
	}
	
	protected function getComponentRelFile($file) {
		$documentation_dir = 'documentation';
		$documentation_dir_len = strlen($documentation_dir);
		
		$subfile = ltrim(str_replace('/', '-', substr($file, strpos($file, $documentation_dir) + $documentation_dir_len)), '-');
		
		return ltrim($subfile, DIRECTORY_SEPARATOR);
	}
	
	protected function normalizeFileContent($file, $replacements = array()) {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Config');
		
		$scripts = <<<EOT
<script type="text/javascript" src="files/js/jquery.2.1.1.min.js"></script>
<script type="text/javascript" src="files/js/toc-ident.js"></script>

EOT;

		$content = file_get_contents($file);
		
		foreach($replacements as $field => $parts) {
			$content = str_replace('[' . $field . ']', $parts, $content);
		}
		
		// check if there are any include commands in this file
		if(preg_match_all('/\[include "(.*)"\]/', $content, $matches, \PREG_SET_ORDER)) {
			foreach($matches as $match) {
				list($pattern, $includefile) = $match;
				
				if(file_exists($includefile)) {
					$content = str_replace($pattern, file_get_contents($includefile), $content);
				}
			}
		}
		
		// check if there are any hostname commands in this file
		if(preg_match_all('/\[hostname "(.*)"\]/', $content, $matches, \PREG_SET_ORDER)) {
			foreach($matches as $match) {
				list($pattern, $configfield) = $match;
				
				$fields = explode('.', $configfield);
				$hostname = $config;
				
				do {
					$field = array_shift($fields);
					$hostname = $hostname[$field];
					
					if(!is_array($hostname)) {
						break;
					}
				} while(count($fields) > 0);				
				
				$hostname = (!is_string($hostname)) ? '' : $hostname;				
				$content = str_replace($pattern, $hostname, $content);
			}
		}
		
		$content = $scripts . "\r\n" . $content;
		
		file_put_contents($file, $content);
	}
	
	protected function copyDirectory($source, $dest, $permissions = 0755) {
		// Check for symlinks
		if (is_link($source)) {
			return symlink(readlink($source), $dest);
		}
		
		// Simple copy for a file
		if (is_file($source)) {
			return copy($source, $dest);
		}
		
		// Make destination directory
		if (!is_dir($dest)) {
			mkdir($dest, $permissions);
		}
		
		// Loop through the folder
		$dir = dir($source);
		while (false !== $entry = $dir->read()) {
			// Skip pointers
			if ($entry == '.' || $entry == '..' || $entry == '.svn') {
				continue;
			}
			
			// Deep copy directories
			$this->copyDirectory("{$source}/{$entry}", "{$dest}/{$entry}", $permissions);
		}
		
		// Clean up
		$dir->close();
		
		return true;
	}
}
?>