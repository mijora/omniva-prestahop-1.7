<div class="panel col-lg-12">
<div class="panel-heading">
<h4>{l s='Omniva orders' mod='omnivaltshipping'} ({$manifestNum})</h4>
</div>

{if $newOrders != null && $page == 1}
<h4>{l s='New orders' mod='omnivaltshipping'}</h4>
<table class="table order" >
	<thead>
		<tr class="nodrag nodrop">
            <th width='5%'>
				<span class="title_box"><input type = "checkbox" id = "select-all"/></span>
			</th>
			<th width='5%'>
				<span class="title_box active">{l s='Id' mod='omnivaltshipping'}</span>
			</th>
			<th width='15%'>
				<span class="title_box">{l s='Customer' mod='omnivaltshipping'}</span>
			</th>
			<th width='15%'>
				<span class="title_box">{l s='Tracking' mod='omnivaltshipping'}</span>
			</th>
			<th width='15%'>
				<span class="title_box">{l s='Update date' mod='omnivaltshipping'}</span>
			</th>
			<th width='15%'>
				<span class="title_box">{l s='Total' mod='omnivaltshipping'}</span>
			</th>
			<th width='15%'>
				<span class="title_box">{l s='Labels' mod='omnivaltshipping'}</span>
			</th>
		</tr>
	</thead>
    <tbody>
	{assign var=result value=''}
    {foreach from=$newOrders key=myId item=i}
                        <tr>
                            <td><input type = "checkbox" class = "selected-orders" value = "{$i.id_order}" /></td>
                            <td>{$i.id_order}</td>
                            <td><a href="{$orderLink}&id_order={$i.id_order}">{$i.firstname} {$i.lastname}</td>
                            <td>{$i.tracking_number}</td>
                            <td>{$i.date_upd}</td>
                            <td>{$i.total_paid}</td>
                            <td>
								<a href="{$labels}&order_ids={$i.id_order}" class="btn btn-success btn-xs" target="_blank">{l s='Labels' mod='omnivaltshipping'}</a>
								<!--<a href="{$manifestLink}&order_ids={$i.id_order}" class="btn btn-success btn-xs" target="_blank">{l s='Manifest' mod='omnivaltshipping'}</a>-->
								{if $i.tracking_number == null}
								<a href="{$orderSkip}{$i.id_order}" class="btn btn-danger btn-xs">{l s='Skip' mod='omnivaltshipping'}</a>
								{/if}
								{* $i.omnivalt_manifest *}
							</td>
							{$result = "{$result},{$i.id_order}"}
							{$manifest = $i.omnivalt_manifest}
                        </tr>
    {/foreach}
                            </tbody>
                        </table>
                        <br/>
			<a href="{$manifestAll}&order_ids={$result}&type=new" data-url = "{$manifestAll}&type=new&order_ids=" class="btn btn-default btn-xs action-call" target='_blank'>{l s='Manifest' mod='omnivaltshipping'}</a>
			<a href="{$labels}&order_ids={$result}" data-url = "{$labels}&order_ids=" class="btn btn-default btn-xs action-call" target='_blank'>{l s='Labels' mod='omnivaltshipping'}</a>
		
						<br/><hr/><br/>
{/if}
{if isset($orders[0]['omnivalt_manifest'])}		
	{assign var=hasOrder value=$orders[0]['omnivalt_manifest']+1}
{else}
	{assign var=hasOrder value=null}
	{* assign var=hasOrder = 'null' *}
{/if}
{if $orders != null}

    <h4>{l s='Generated' mod='omnivaltshipping'}</h4>
        <table class="table order" >
            <thead>
                <tr class="nodrag nodrop">
                    <th width='5%'>
                        <span class="title_box active">{l s='Id' mod='omnivaltshipping'}</span>
                    </th>
                    <th width='15%'>
                        <span class="title_box">{l s='Customer' mod='omnivaltshipping'}</span>
                    </th>
                    <th width='15%'>
                        <span class="title_box">{l s='Tracking' mod='omnivaltshipping'}</span>
                    </th>
                    <th width='15%'>
                        <span class="title_box">{l s='Update date' mod='omnivaltshipping'}</span>
                    </th>
                    <th width='15%'>
                        <span class="title_box">{l s='Total' mod='omnivaltshipping'}</span>
                    </th>
                    <th width='15%'>
                        <span class="title_box">{l s='Labels' mod='omnivaltshipping'}</span>
                    </th>
				</tr>
		</thead>
    	<tbody>


		{assign var=result value=''}
        {foreach from=$orders key=myId item=i}
			{if isset($manifestOrd) && $i.omnivalt_manifest != $manifestOrd}
				</table>
				{if $myId !=0}
                <br/>
				<a href="{$manifestAll}&order_ids={$result}" class="btn btn-default btn-xs" target='_blank'>{l s='Manifest' mod='omnivaltshipping'}</a>
				<a href="{$labels}&order_ids={$result}" class="btn btn-default btn-xs" target='_blank'>{l s='Labels' mod='omnivaltshipping'}</a><br>
				{assign var=result value=''}
				{/if}
				<br>
				<table class="table order" >
				<thead>
					<tr class="nodrag nodrop">
                        <th width='5%'>
                            <span class="title_box active">{l s='Id' mod='omnivaltshipping'}</span>
                        </th>
                        <th width='15%'>
                            <span class="title_box">{l s='Customer' mod='omnivaltshipping'}</span>
                        </th>
                        <th width='15%'>
                            <span class="title_box">{l s='Tracking' mod='omnivaltshipping'}</span>
                        </th>
                        <th width='15%'>
                            <span class="title_box">{l s='Update date' mod='omnivaltshipping'}</span>
                        </th>
                        <th width='15%'>
                            <span class="title_box">{l s='Total' mod='omnivaltshipping'}</span>
                        </th>
                        <th width='15%'>
                            <span class="title_box">{l s='Labels' mod='omnivaltshipping'}</span>
                        </th>
					</tr>
				</thead>
				<tbody>
			{/if}
                        <tr>
                            <td>{$i.id_order}</td>
                            <td><a href="{$orderLink}&id_order={$i.id_order}">{$i.firstname} {$i.lastname}</td>
                            <td>{$i.tracking_number}</td>
                            <td>{$i.date_upd}</td>
                            <td>{$i.total_paid}</td>
                            <td>
								<a href="{$labels}&order_ids={$i.id_order}" class="btn btn-success btn-xs" target="_blank">{l s='Labels' mod='omnivaltshipping'}</a>
								<!--<a href="{$manifestLink}&order_ids={$i.id_order}" class="btn btn-success btn-xs" target="_blank">{l s='Manifest' mod='omnivaltshipping'}</a>-->
								{if $i.tracking_number == null}
								<a href="{$cancelSkip}{$i.id_order}" class="btn btn-danger btn-xs">{l s='Add to manifest' mod='omnivaltshipping'}</a>
								{/if}
							{* $i.omnivalt_manifest *}
							</td>
{$result = "{$result},{$i.id_order}"}
{$manifestOrd = $i.omnivalt_manifest}
                        </tr>
                        {/foreach}
	{/if}
 {if $orders != null}
</tbody>
</table>
<br>
<a href="{$manifestAll}&order_ids={$result}" class="btn btn-default btn-xs" target='_blank'>{l s='Manifest' mod='omnivaltshipping'}</a>
<a href="{$labels}&order_ids={$result}" class="btn btn-default btn-xs" target='_blank'>{l s='Labels' mod='omnivaltshipping'}</a><br>
<div class="text-center">
{$pagination_content}
</div>
</div>
{/if}
<script>
$(document).ready(function(){
    $('.showall').hide();
    $('.pagination_next b').hide();
    $('.pagination_previous b').hide();
    $('#select-all').on('click',function(){
        var checked = $(this).prop('checked');
        $('.selected-orders').prop('checked',checked);
    });
    $('.action-call').on('click',function(e){
        //e.preventDefault();
        var ids = '';
        $('.selected-orders:checked').each(function(){
            ids += "," + $(this).val();
        });
        if (ids == ""){
            alert('Pasirinkite užsakymus');
            return false;
        } else {
            $(this).attr('href', $(this).data('url') + ids);
        }
            
    });
});
</script>
