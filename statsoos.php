<?php

class StatsOos extends Module
{
  private $_html = '';

  function __construct()
  {
    $this->v14 = _PS_VERSION_ >= "1.4.0.0";
    $this->name = 'statsoos';
    if ($this->v14)
      $this->tab = 'administration';
    else
      $this->tab = 'Stats';
    $this->version = '0.5';
    $this->page = basename(__FILE__, '.php');

    parent::__construct();

    $this->displayName = $this->l('Customer out of stock');
    $this->description = $this->l('Shows customers who are waiting for out of stock products.');
  }

  public function install()
  {
    return (parent::install() AND $this->registerHook('AdminStatsModules'));
  }

  private function getOosPages($resultperpage, $page, $limit) 
  {
    return Db::getInstance()->ExecuteS('
      SELECT `id_customer`, `customer_email`, `id_product`, `id_product_attribute`
      FROM `'._DB_PREFIX_.'mailalert_customer_oos`
      '.($limit == 1 ? '
      ORDER BY `id_product` DESC
      LIMIT '.(($page*$resultperpage)-$resultperpage).', '.$resultperpage 
      : '').''
    );
  }

  private function deleteOos($f)
  {
    return Db::getInstance()->Execute('
      DELETE FROM `'._DB_PREFIX_.'mailalert_customer_oos` WHERE
      `id_customer` = "'.$f[0].'" AND
      `customer_email` = "'.$f[1].'" AND
      `id_product` = "'.$f[2].'" AND
      `id_product_attribute` = "'.$f[3].'"');
  }

  public function hookAdminStatsModules($params)
  {
    global $cookie;

    $page = !empty($_POST['page']) ? $_POST['page'] : '1';

    if (!empty($_POST['firstpage_x']))
      $page = 1;
    elseif (!empty($_POST['pagedown_x']))
      $page = $page - 1;
    elseif (!empty($_POST['pageup_x']))
      $page = $page + 1;
    elseif (!empty($_POST['lastpage_x']))
      $page = $_POST['nblastpage'];
    else
      $page = $page;

    $resultperpage = 20;

    if (Tools::isSubmit('submitDelOos')) {
      $oosBox = Tools::getValue('oosBox');
      foreach ($oosBox as $oos)
	$this->deleteOos(explode('|', $oos));
    }

    if ($this->v14)
      $this->_html = '
      <form name="submitStatsOos" method="post" action="index.php?tab=AdminStatsModules&token='.Tools::getValue('token').'&module='.$this->name.'">';
    else
      $this->_html = '
      <form name="submitStatsOos" method="post" action="index.php?tab=AdminStats&token='.Tools::getValue('token').'&module='.$this->name.'">';

    $this->getOosPages($resultperpage, $page, 0);
    $totalOosPages = Db::getInstance()->NumRows();
    $totalpage = ceil($totalOosPages/$resultperpage);
    if ($totalpage == 0)
      $totalpage = 1;
    $oosPages = (array)$this->getOosPages($resultperpage, $page, 1);

    $this->_html .= '
      <fieldset class="width3 space"><legend><img src="../modules/'.$this->name.'/img/setting.gif" /> '.$this->l('Pagination').'</legend>
      <p>
      '.$this->l('Go directly to page').' 
      <select onChange="submit();" name="page">
      ';
    for ($i = 1 ; $i <= $totalpage ; $i++)
    {
      $this->_html .='<option '.($i == $page ? 'selected="selected "': '').'value="'.$i.'">&nbsp;'.$i.'&nbsp;</option>';
    } 
    $this->_html .= '
      </select> / '.$totalpage.'
      </p>
      <p style="text-align: center; display: block; width:600px; border-top: 7px;">
      <input '.($page <= 1 ? 'style="display: none" ' : '').'name="firstpage" type="image" onclick="submit();" src="../modules/'.$this->name.'/img/list-prev2.gif"/>
      &nbsp;<input '.($page <= 1 ? 'style="display: none" ' : '').'name="pagedown" type="image" onclick="submit();" src="../modules/'.$this->name.'/img/list-prev.gif"/>
      &nbsp;'.$this->l('Page').' '.$page.' / '.$totalpage.'
      &nbsp;<input '.($page >= $totalpage ? 'style="display: none" ' : '').'name="pageup" type="image" onclick="submit();" src="../modules/'.$this->name.'/img/list-next.gif"/>
      &nbsp;<input '.($page >= $totalpage ? 'style="display: none" ' : '').'name="lastpage" type="image" onclick="submit();" src="../modules/'.$this->name.'/img/list-next2.gif"/>
      <input type="hidden" name="nblastpage" value="'.$totalpage.'">
      </p>
      </fieldset>';

    $this->_html .= '<fieldset class="width3 space"><legend><img src="../modules/'.$this->name.'/img/logo.gif" /> '.$this->l('Visitor(s)').'</legend>';
    $this->_html .= $this->l('Total:').' '.intval($totalOosPages).'
      <table cellpadding="0" cellspacing="0" class="table space">
      <tr>
      <th><input type="checkbox" name="checkme" onclick="checkDelBoxes(this.form, \'oosBox[]\', this.checked)" /></th>
      <th style="text-align: center;">'.$this->l('Customer').'</th><th style="text-align: center;">'.$this->l('E-mail').'</th><th style="text-align: center;">'.$this->l('Product').'</th><th style="text-align: center;">'.$this->l('Attribute').'</th></tr>';
    foreach ($oosPages as $oosPage)
    {
      $customer = new Customer($oosPage['id_customer']);
      $product = new Product($oosPage['id_product']);
      $attrName = $oosPage['id_product_attribute'];
      $quantity = $product->quantity;
      if ($customer->id) {
	$customerName = $customer->firstname.' '.$customer->lastname;
	$customerEmail = $customer->email;
      }
      else {
	$customerName = '-';
	$customerEmail = $oosPage['customer_email'];
      }
      if ($attrName == 0)
	$attrName = '-';
      $attrs = $product->getAttributeCombinaisons(intval($cookie->id_lang));
      foreach ($attrs as $attr) {
	if ($attr['id_product_attribute'] == $oosPage['id_product_attribute']) {
	  $quantity = $attr['quantity'];
	  $attrName = $attr['group_name'].': '.$attr['attribute_name'];
	}
      }
      $url = _PS_BASE_URL_.__PS_BASE_URI__.'product.php?id_product='.intval($product->id);
      if (!$product->active)
	$productColor = 'style="color:grey;"';
      elseif ($quantity > 0)
	$productColor = 'style="color:green;"';
      else
	$productColor = '';
      if (Validate::isEmail($customerEmail))
	$emailColor = '';
      else
	$emailColor = 'color:red;';
      $this->_html .= '
	<tr>
	<td style="text-align: center;"><input type="checkbox" name="oosBox[]" value="'.implode('|', $oosPage).'" /></td>
	<td style="text-align: center;">'.$customerName.'</td>
	<td style="text-align: center; '.$emailColor.'">'.$customerEmail.'</td>
	<td style="text-align: left;"><a href="'.$url.'"'.$productColor.'>#'.$oosPage['id_product'].' '.$product->name[intval($cookie->id_lang)].'</a></td>
	<td style="text-align: center;">'.$attrName.'</td>
	</tr>';
    }
    $this->_html .= '</table>';
    $this->_html .= '<br /><input type="submit" name="submitDelOos" class="button" value="'.$this->l('Delete selection').'">';
    $this->_html .= '</fieldset>';
    $this->_html .= '
      <fieldset class="width3 space"><legend><img src="../modules/'.$this->name.'/img/setting.gif" /> '.$this->l('Pagination').'</legend>
      <p style="text-align: center; display: block; width:600px; border-top: 7px;">
      <input '.($page <= 1 ? 'style="display: none" ' : '').'name="firstpage" type="image" onclick="submit();" src="../modules/'.$this->name.'/img/list-prev2.gif"/>
      &nbsp;<input '.($page <= 1 ? 'style="display: none" ' : '').'name="pagedown" type="image" onclick="submit();" src="../modules/'.$this->name.'/img/list-prev.gif"/>
      &nbsp;'.$this->l('Page').' '.$page.' / '.$totalpage.'
      &nbsp;<input '.($page >= $totalpage ? 'style="display: none" ' : '').'name="pageup" type="image" onclick="submit();" src="../modules/'.$this->name.'/img/list-next.gif"/>
      &nbsp;<input '.($page >= $totalpage ? 'style="display: none" ' : '').'name="lastpage" type="image" onclick="submit();" src="../modules/'.$this->name.'/img/list-next2.gif"/>
      <input type="hidden" name="nblastpage" value="'.$totalpage.'">
      </p>
      </fieldset>
      </form>';
    return $this->_html;
  }

}

?>
