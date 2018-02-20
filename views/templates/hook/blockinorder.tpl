<div class="tab-content omnivalt">
    <div class="panel">
    <div class="panel-heading">
		<i class="icon-tags"></i> OMNIVALT
	</div>
<div class="row">
<div class="col-lg-12">
<div class="omnivalt_order_config" style="border:1px solid #eee; border-radius: 4px; padding:4px; margin-bottom:8px;">
    
    <form action="{$moduleurl}" method="post" id="omnivaltOrderSubmitForm">
    <div class="col-md-6">
        <div class="field-row"><span>{l s="Packets" mod='omnivaltshipping'}: </span><span><input type="text" name="packs" value="1" /></span></div>
        <div class="field-row"><span>{l s="Weight" mod='omnivaltshipping'}: </span><span><input type="text" name="weight" value="{$total_weight}" /></span></div>
        <div class="field-row row">
            <div class="col-sm-6">
                <span>{l s="C.O.D." mod='omnivaltshipping'}: </span><span><select name="is_cod" >
                        <option value="0">{l s='No' mod='omnivaltshipping'}</option>
                        <option value="1" {if $is_cod} selected="selected" {/if}>{l s='Yes' mod='omnivaltshipping'}</option>
                    </select></span>
            </div>
            <div class="col-sm-6">{l s="C.O.D. amount" mod='omnivaltshipping'}: <input type="text" name="cod_amount" value="{$total_paid_tax_incl}"  /></div>
        </div>


    </div>
    <div class="col-md-6">
        <div class="field-row omnivalt-carrier">{l s='Carrier' mod='omnivaltshipping'}: <select name="carrier" class = "chosen">
                {$carriers}
            </select>
        </div>
      
        <div class="field-row omnivalt-terminal">{l s='Parcel terminal' mod='omnivaltshipping'}: <select name="parcel_terminal" class = "chosen">
                {$parcel_terminals}
            </select>
        </div>
      


    </div>
    <div class="clearfix"></div>

    <div class="response">{if $error != ''}<div class="alert alert-danger">{$error}</div>{/if}</div>
    <div class="clearfix"></div>
    <button type="button" name="omnivalt_save" style = "float:left; margin:5px;" id="omnivaltOrderSubmitBtn" class="btn btn-default"><i class="icon-save"></i> {l s="Save"}</button>
    
    </form>

    <form method="POST" action="{$printlabelsurl}" id="omnivaltOrderPrintLabelsForm" target="_blank" style = "display:inlne-block; margin:5px;">
   
        <button type="submit" name="omnivalt_printlabel" id="omnivaltOrderPrintLabels" class="btn btn-default"><i class="icon-tag"></i> {l s="Generate label" mod='omnivaltshipping'}</button>
    </form>
    {if $label_url != ''}
        <a href = "{$label_url}" target="_blank" id = "omnivalt_print_btn" style="display:inlne-block; margin:5px;" class = "btn btn-default"  mod='omnivaltshipping'><i class="icon-print"></i> {l s="Print label" mod='omnivaltshipping'}</a>
    {/if}
</div>
</div>
</div>
</div>
</div>
<script type="text/javascript">
$(document).ready(function(){
    var omnivaltPanel = $('.tab-content.omnivalt');
    //omnivaltPanel.insertAfter( ".panel.kpi-container" );
    var omnivalt_terminal_carrier = '{$omnivalt_parcel_terminal_carrier_id}';
    $('.omnivalt-carrier').on('change','select',function(){
        if ($(this).val() == omnivalt_terminal_carrier)
            $('.omnivalt-terminal').show();
        else
            $('.omnivalt-terminal').hide();
    });
    $('.omnivalt-carrier select').trigger('change');
    

    
     function labelOrderInfo(){
        $("#omnivaltOrderPrintLabels").attr('disabled','disabled');
        var formData = $("#omnivaltOrderPrintLabelsForm").serialize()+'&'+$.param({
					ajax: "1",
                    token: "{getAdminToken tab='AdminOrders'}",
					order_id: "{$order_id}",

					});


        $.ajax({
				type:"POST",
                url: "{$printlabelsurl}",
				async: false,
				dataType: "json",
				data : formData,
				success : function(res)
				{
					//disable the inputs
                    if(typeof res.error !== "undefined"){
                        $("#omnivaltOrderSubmitForm").find('.response').html('<div class="alert alert-danger">'+res.error+'</div>');
                        $("#omnivaltOrderPrintLabels").removeAttr('disabled');
                    }else{
                        $("#omnivaltOrderSubmitForm").find('.response').html('<div class="alert alert-success">{l s="Successfully added." mod='omnivaltshipping'}</div>');
                        $("#omnivaltOrderPrintLabels").removeAttr('disabled');
                        window.location.href = location.href
                    }


				},
                error: function(res){

                }
			});
            return $("#omnivaltOrderPrintLabels").is(":disabled");
    }
    
    function saveOrderInfo(){
        $("#omnivaltOrderSubmitBtn").attr('disabled','disabled');
        var formData = $("#omnivaltOrderSubmitForm").serialize()+'&'+$.param({
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
				success : function(res)
				{
					//disable the inputs
                    if(typeof res.error !== "undefined"){
                        $("#omnivaltOrderSubmitForm").find('.response').html('<div class="alert alert-danger">'+res.error+'</div>');
                        $("#omnivaltOrderSubmitBtn").removeAttr('disabled');
                    }else{
                        $("#omnivaltOrderSubmitForm").find('.response').html('<div class="alert alert-success">{l s="Successfully added." mod='omnivaltshipping'}</div>');
                        $("#omnivaltOrderSubmitBtn").removeAttr('disabled');
                        $("#omnivalt_print_btn").hide();
                    }


				},
                error: function(res){

                }
			});
            return $("#omnivaltOrderSubmitBtn").is(":disabled");
    }
    $("#omnivaltOrderPrintLabels").unbind('click').bind('click',function(e){
        $(this).attr('disabled','disabled');
        $("#omnivaltOrderSubmitForm").find('.response').html('');
        e.preventDefault();
        e.stopPropagation();
        labelOrderInfo();
        
        return false;
    });
    $("#omnivaltOrderSubmitBtn").unbind('click').bind('click',function(e){
        $(this).attr('disabled','disabled');
        $("#omnivaltOrderSubmitForm").find('.response').html('');
        e.preventDefault();
        e.stopPropagation();
        saveOrderInfo();
        
        return false;
    });
});
</script>
