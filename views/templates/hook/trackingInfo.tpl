{*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
*}
<div class="box">
<div class="row">
    {foreach $tracking_info as $number=>$info}
        <div class="col-xs-12 col-sm-6">
            <h3 class="page-subheading">{l s='Tracking information' mod='omnivaltshipping'} - {$number}</h3>
            <div class="table_block table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th class="item">{l s='Date' mod='omnivaltshipping'}</th>
                            <th class="last_item">{l s='Event' mod='omnivaltshipping'}</th>
                            <th class="last_item">{l s='Place' mod='omnivaltshipping'}</th>
                        </tr>
                    </thead>
                    <tbody>
                    {foreach $info['progressdetail'] as $event}
                        <tr>
                            <td>{date_format($event['deliverydate'], 'Y-m-d H:i:s')}</td>
                            <td>{$event['activity']}</td>
                            <td>{$event['deliverylocation']}</td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    {/foreach}
</div>
</div>
<script type="text/javascript">
    var omniva_tracking = "{$tracking_number}";
    var omniva_country = "{$country_code}";
</script>