{*
* Enviamo Connector - Display Admin Order Hook
* Muestra información de Enviamo en la página de pedido del admin
*}

<div class="panel" id="enviamo-order-info">
    <div class="panel-heading">
        <i class="icon-truck"></i>
        {l s='Enviamo - Información de Envío' mod='enviamo_connector'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <p>
                    <a href="{$enviamo_dashboard_url}" target="_blank" class="btn btn-primary">
                        <i class="icon-external-link"></i>
                        {l s='Ver en Dashboard de Enviamo' mod='enviamo_connector'}
                    </a>
                </p>
                <p class="text-muted">
                    <i class="icon-info-circle"></i>
                    {l s='Gestiona el envío de este pedido desde el Dashboard de Enviamo.' mod='enviamo_connector'}
                </p>
            </div>
        </div>
    </div>
</div>

<style>
#enviamo-order-info {
    margin-top: 20px;
}
</style>
