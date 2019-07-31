{*
* 2007-2014 PrestaShop
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
*         DISCLAIMER   *
* *************************************** */
/* Do not edit or add to this file if you wish to upgrade Prestashop to newer
* versions in the future.
* ****************************************************
*}

<!--  omnivalt_parcel_terminal_carrier [begin] -->
<script type="text/javascript">
    var omnivalt_parcel_terminal_carrier_id = {$omnivalt_parcel_terminal_carrier_id};
    var omnivadata = [];
    omnivadata.text_select_terminal = '{l s='Select terminal' mod='omnivaltshipping'}';
    omnivadata.text_search_placeholder = '{l s='Enter postcode' mod='omnivaltshipping'}';
    omnivadata.not_found = '{l s='Place not found' mod='omnivaltshipping'}';
    omnivadata.text_enter_address = '{l s='Enter postcode/address' mod='omnivaltshipping'}';
    omnivadata.text_show_in_map = '{l s='Show in map' mod='omnivaltshipping'}';
    omnivadata.text_show_more = '{l s='Show more' mod='omnivaltshipping'}';
    omnivadata.omniva_plugin_url = '{$module_url}';
    var omnivalt_parcel_terminal_error = '{l s='Please select parcel terminal' mod='omnivaltshipping'}';
    var omnivaltdelivery_controller = '{$link->getModuleLink('omnivaltshipping', 'ajax') nofilter}';
</script>


    <script type="text/javascript">
      var select_terminal = "{l s='Pasirinkti terminalą'  mod='omnivaltshipping'}";
      var text_search_placeholder = "{l s='įveskite adresą' mod='omnivaltshipping'}";
    </script>

<script>
    var omnivaSearch = "{l s='Įveskite adresą paieškos laukelyje, norint surasti paštomatus'  mod='omnivaltshipping'}";
    {literal}
        var modal = document.getElementById('omnivaLtModal');
        window.document.onclick = function(event) {
            if (event.target == modal || event.target.id == 'omnivaLtModal' || event.target.id == 'terminalsModal') {
              document.getElementById('omnivaLtModal').style.display = "none";
            } 
        }
    {/literal}
</script>
<div id="omnivaLtModal" class="modal">
    <div class="omniva-modal-content">
            <div class="omniva-modal-header">
            <span class="close" id="terminalsModal">&times;</span>
            <h5 style="display: inline">{l s='Omniva parcel terminals' mod='omnivaltshipping'}</h5>
            </div>
            <div class="omniva-modal-body" style="/*overflow: hidden;*/">
                <div id = "omnivaMapContainer"></div>
                <div class="omniva-search-bar" >
                    <h4 style="margin-top: 0px;">{l s='Parcel terminals addresses' mod='omnivaltshipping'}</h4>
                    <div id="omniva-search">
                    <form>
                    <input type = "text" placeholder = "{l s='Enter postcode' mod='omnivaltshipping'}"/>
                    <button type = "submit" id="map-search-button"></button>
                    </form>                    
                    <div class="omniva-autocomplete scrollbar" style = "display:none;"><ul></ul></div>
                    </div>
                    <div class = "omniva-back-to-list" style = "display:none;">{l s='Back to list' mod='omnivaltshipping'}</div>
                    <div class="found_terminals scrollbar" id="style-8">
                      <ul>
                      
                      </ul>
                    </div>
                </div>
        </div>
    </div>
</div>
