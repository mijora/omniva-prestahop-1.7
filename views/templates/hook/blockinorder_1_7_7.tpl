<div class="product-row row omniva-block">
    <div class="col-md-12 d-print-block left-column">
        <div class="card">
            <div class="card-header">
                <h3 class="card-header-title">
                    <i class="material-icons">local_shipping</i>
                    {l s="Omniva Shipping" mod='omnivaltshipping'}
                </h3>
            </div>
            <div class="card-body">
                {if $error}
                    {$error}
                {/if}
                <form action="{$moduleurl}" method="POST" id="omnivaltOrderSubmitForm">
                    <div class="form-row">
                        <div class="form-group col-md-6 col-xs-12">
                            <label for="omniva-packs">{l s="Packets" mod='omnivaltshipping'}:</label>
                            <input id="omniva-packs" type="text" name="packs" value="1" class="form-control" />
                        </div>
                        <div class="form-group col-md-6 col-xs-12">
                            <label for="omniva-weight">{l s="Weight" mod='omnivaltshipping'}:</label>
                            <input id="omniva-weight" type="text" name="weight" value="{$total_weight}" class="form-control" />
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6 col-xs-12">
                            <label for="omniva-cod">{l s="C.O.D." mod='omnivaltshipping'}:</label>
                            <select name="is_cod" id="omniva-cod" class="form-control">
                                <option value="0">{l s='No' mod='omnivaltshipping'}</option>
                                <option value="1" {if $is_cod} selected {/if}>{l s='Yes' mod='omnivaltshipping'}</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6 col-xs-12">
                            <label for="omniva-cod-amount">{l s="C.O.D. amount" mod='omnivaltshipping'}:</label>
                            <input id="omniva-cod-amount" type="text" name="cod_amount" value="{$total_paid_tax_incl}" class="form-control" />
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-12">
                            <label for="omniva-carrier">{l s='Carrier' mod='omnivaltshipping'}:</label>
                            <select id="omniva-carrier" name="carrier" class="form-control">
                                {$carriers}
                            </select>
                        </div>
                    </div>
                    <div class="form-row omniva-terminal-block">
                        <div class="form-group col-md-12">
                            <label for="omniva-parcel-terminal">{l s='Parcel terminal' mod='omnivaltshipping'}:</label>
                            <select id="omniva-parcel-terminal" name="parcel_terminal" class="form-control"
                                data-toggle="select2" data-minimumresultsforsearch="3" aria-hidden="true">
                                {$parcel_terminals}
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-12 d-flex justify-content-end">
                            <button type="button" name="omnivalt_save" id="omnivaltOrderSubmitBtn" class="btn btn-default"><i class="material-icons">save</i> {l s="Save"}</button>
                        </div>
                    </div>
                </form>
                <div class="omniva-response alert d-none" role="alert"></div>
            </div>
            <div class="card-footer omniva-footer d-flex justify-content-between">
                <form method="POST" action="{$printlabelsurl}" id="omnivaltOrderPrintLabelsForm" target="_blank">
                    <button type="submit" name="omnivalt_printlabel" id="omnivaltOrderPrintLabels" class="btn btn-default"><i class="material-icons">tag</i> {l s="Generate label" mod='omnivaltshipping'}</button>
                </form>
                {if $label_url != ''}
                    <a href="{$label_url}" target="_blank" id="omnivalt_print_btn" class="btn btn-default"  mod='omnivaltshipping'><i class="material-icons">print</i> {l s="Print label" mod='omnivaltshipping'}</a>
                {/if}
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function(){
    let omnivaltPanel = $('.omniva-block');
    let omnivalt_terminal_carrier = '{$omnivalt_parcel_terminal_carrier_id}';

    $('#omniva-carrier').on('change', function() {
        let action = $(this).val() == omnivalt_terminal_carrier ? 'remove' : 'add';
        $('.omniva-terminal-block')[action + 'Class']('d-none');
    });
    $('#omniva-carrier').trigger('change');

    function disableButton(id, status) {
        omnivaltPanel[0].querySelector(id).disabled = status;
    }

    function cleanResponse() {
        $('.omniva-response')
            .removeClass(['alert-danger', 'alert-warning', 'alert-success'])
            .addClass('d-none')
            .html('');
    }

    function showResponse(msg, type) {
        cleanResponse();
        $('.omniva-response')
            .removeClass('d-none')
            .addClass(type)
            .html(msg);
    }
    
    function labelOrderInfo() {
        disableButton('#omnivaltOrderPrintLabels', true);

        let formData = $("#omnivaltOrderPrintLabelsForm")
            .serialize() + '&' + $.param({
                ajax: "1",
                token: "{getAdminToken tab='AdminOrders'}",
                order_id: "{$order_id}"
            });

        $.ajax({
            type:"POST",
            url: "{$printlabelsurl}",
            async: false,
            dataType: "json",
            data : formData,
            success : function(res)
            {
                disableButton('#omnivaltOrderPrintLabels', false);

                if(typeof res.error !== "undefined"){
                    showResponse(res.error, 'alert-danger');
                    return;
                }

                showResponse('{l s="Successfully added." mod="omnivaltshipping"}', 'alert-success');
                
                setTimeout(function() {
                    window.location.href = location.href
                }, 1000);
            },
            error: function(res){
                disableButton('#omnivaltOrderPrintLabels', false);
            }
        });
    }
    
    function saveOrderInfo(){
        disableButton('#omnivaltOrderSubmitBtn', true);
        var formData = $("#omnivaltOrderSubmitForm")
            .serialize() + '&' + $.param({
                ajax: "1",
                token: "{getAdminToken tab='AdminOrders'}",
                order_id: "{$order_id}",
            });


        $.ajax({
            type:"POST",
            url: "{$moduleurl}",
            async: false,
            dataType: "json",
            data : formData,
            success : function(res) {
                disableButton('#omnivaltOrderSubmitBtn', false);

                if(typeof res.error !== "undefined"){
                    showResponse(res.error, 'alert-danger');
                    return;
                }

                showResponse('{l s="Successfully added." mod="omnivaltshipping"}', 'alert-success');

                $("#omnivalt_print_btn").addClass('d-none');
            },
            error: function(res){
                disableButton('#omnivaltOrderSubmitBtn', false);
            }
        });
    }

    $("#omnivaltOrderPrintLabels").unbind('click').bind('click',function(e){
        disableButton('#omnivaltOrderPrintLabels', true);
        e.preventDefault();
        e.stopPropagation();
        labelOrderInfo();
        
        return false;
    });

    $("#omnivaltOrderSubmitBtn").unbind('click').bind('click',function(e){
        disableButton('#omnivaltOrderSubmitBtn', true);
        e.preventDefault();
        e.stopPropagation();
        saveOrderInfo();
        
        return false;
    });
});
</script>
