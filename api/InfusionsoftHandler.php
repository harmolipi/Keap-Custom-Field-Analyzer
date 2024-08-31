<?php

namespace Nikobirbilis\KeapCustomFieldAnalyzer\Api;

require_once '../vendor/autoload.php';

use Infusionsoft\Infusionsoft;

class InfusionsoftHandler
{
  private array $get;
  private $infusionsoft;

  public function __construct($get, $infusionsoft_creds = [])
  {
    $this->infusionsoft = new Infusionsoft($infusionsoft_creds);
    $this->get = $get;

    switch ($get['type']) {
      case 'campaigns':
    }
  }

  private function getCampaigns()
  {
    $campaigns = $this->infusionsoft->campaigns()->with('sequences')->get()->toArray();
  }
}
