jQuery(document).ready(function($){
  if (help_class_name  == 'AdminOrders'){
    var bulk_dropdown = $('.bulk-actions ul.dropdown-menu');
    bulk_dropdown.append('<li><a href="#" onclick="sendOmnivaltBulkAction($(this).closest(\'form\').get(0), \''+omnivalt_admin_action_labels+'\',$(this),true);"><i class="icon-cloud-download"></i>&nbsp;'+omnivalt_bulk_labels+'</a></li>');
    bulk_dropdown.append('<li><a href="#" onclick="sendOmnivaltBulkAction($(this).closest(\'form\').get(0), \''+omnivalt_admin_action_manifests+'\',$(this),true);"><i class="icon-cloud-download"></i>&nbsp;'+omnivalt_bulk_manifests+'</a></li>');
 }
});

function sendOmnivaltBulkAction(form, action, object, reload)
{
  var order_ids = '';
  $("input[name='orderBox[]']:checked").each(function( index ) {
    order_ids += $( this ).val() +',';
  });
  if (order_ids) {
    object.attr('href',action+'&order_ids='+order_ids);
    object.attr('target','_blank');
    if (reload){
      setTimeout(function(){
        window.location.href = location.href;
      }, 5000);
    }
  } else {
    alert('Select orders');
  }
  return false;
}