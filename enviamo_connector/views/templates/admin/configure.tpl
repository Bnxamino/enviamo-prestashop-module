{*
* Enviamo Connector - Configuration Template
* Vista principal de configuración del módulo
*}

<div class="panel" id="enviamo-connector-config">
    <div class="panel-heading">
        <i class="icon-cog"></i> {l s='Configuración Enviamo Connector' mod='enviamo_connector'}
    </div>

    {if $is_connected}
        {* ESTADO: CONECTADO *}
        <div class="panel-body">
            <div class="alert alert-success">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <i class="icon-check"></i>
                <strong>{l s='¡Conectado con Enviamo!' mod='enviamo_connector'}</strong>
                <p>{l s='Tu tienda está sincronizada y lista para enviar pedidos.' mod='enviamo_connector'}</p>
            </div>

            {* Información de la Conexión *}
            <div class="row">
                <div class="col-md-6">
                    <div class="well">
                        <h4><i class="icon-info-circle"></i> {l s='Información de Conexión' mod='enviamo_connector'}</h4>
                        <dl class="dl-horizontal">
                            <dt>{l s='ID de Tienda:' mod='enviamo_connector'}</dt>
                            <dd><code>#{$store_id}</code></dd>

                            <dt>{l s='Última Sincronización:' mod='enviamo_connector'}</dt>
                            <dd>{$last_sync|date_format:"%d/%m/%Y %H:%M"}</dd>

                            <dt>{l s='Estado:' mod='enviamo_connector'}</dt>
                            <dd><span class="badge badge-success">{l s='Activo' mod='enviamo_connector'}</span></dd>
                        </dl>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="well">
                        <h4><i class="icon-cogs"></i> {l s='Acciones Rápidas' mod='enviamo_connector'}</h4>
                        <div class="btn-group-vertical" style="width: 100%;">
                            <a href="{$enviamo_dashboard_url}" target="_blank" class="btn btn-primary btn-lg">
                                <i class="icon-external-link"></i>
                                {l s='Abrir Dashboard de Enviamo' mod='enviamo_connector'}
                            </a>
                            <button type="button" class="btn btn-default" onclick="testEnviamoConnection()">
                                <i class="icon-refresh"></i>
                                {l s='Probar Conexión' mod='enviamo_connector'}
                            </button>
                            <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" style="display: inline;">
                                <button type="submit" name="disconnect_enviamo" class="btn btn-danger btn-block" onclick="return confirm('{l s='¿Estás seguro de que deseas desconectar esta tienda de Enviamo?' mod='enviamo_connector'}');">
                                    <i class="icon-unlink"></i>
                                    {l s='Desconectar de Enviamo' mod='enviamo_connector'}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {* Estadísticas Rápidas *}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <h4><i class="icon-bar-chart"></i> {l s='Actividad Reciente' mod='enviamo_connector'}</h4>
                    <div class="alert alert-info">
                        <p><i class="icon-info-circle"></i> {l s='Toda la configuración se gestiona desde el Dashboard de Enviamo. Haz clic en el botón de arriba para acceder.' mod='enviamo_connector'}</p>
                    </div>
                </div>
            </div>

            {* Logs Recientes *}
            {if $recent_logs}
                <div class="row" style="margin-top: 20px;">
                    <div class="col-md-12">
                        <h4><i class="icon-list"></i> {l s='Logs Recientes' mod='enviamo_connector'}</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{l s='Fecha' mod='enviamo_connector'}</th>
                                        <th>{l s='Nivel' mod='enviamo_connector'}</th>
                                        <th>{l s='Mensaje' mod='enviamo_connector'}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach from=$recent_logs item=log}
                                        <tr>
                                            <td>{$log.created_at|date_format:"%d/%m/%Y %H:%M"}</td>
                                            <td>
                                                {if $log.type == 'error'}
                                                    <span class="badge badge-danger">{l s='Error' mod='enviamo_connector'}</span>
                                                {elseif $log.type == 'warning'}
                                                    <span class="badge badge-warning">{l s='Advertencia' mod='enviamo_connector'}</span>
                                                {elseif $log.type == 'success'}
                                                    <span class="badge badge-success">{l s='Éxito' mod='enviamo_connector'}</span>
                                                {else}
                                                    <span class="badge badge-info">{l s='Info' mod='enviamo_connector'}</span>
                                                {/if}
                                            </td>
                                            <td>{$log.message|escape:'htmlall':'UTF-8'}</td>
                                        </tr>
                                    {/foreach}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            {/if}
        </div>

    {else}
        {* ESTADO: NO CONECTADO *}
        <div class="panel-body">
            <div class="alert alert-warning">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <i class="icon-warning-sign"></i>
                <strong>{l s='No conectado' mod='enviamo_connector'}</strong>
                <p>{l s='Conecta tu tienda PrestaShop con Enviamo para empezar a sincronizar pedidos automáticamente.' mod='enviamo_connector'}</p>
            </div>

            <div class="row">
                <div class="col-md-8 col-md-offset-2">
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                <i class="icon-rocket"></i>
                                {l s='Conectar con Enviamo' mod='enviamo_connector'}
                            </h3>
                        </div>
                        <div class="panel-body text-center">
                            {* Opción 1: OAuth 1-Click *}
                            <div style="margin-bottom: 30px;">
                                <h4>{l s='Opción 1: Conexión en 1 Click (Recomendada)' mod='enviamo_connector'}</h4>
                                <p class="text-muted">{l s='La forma más rápida y segura de conectar tu tienda.' mod='enviamo_connector'}</p>
                                <a href="{$oauth_url}" class="btn btn-lg btn-primary" style="margin-top: 10px;">
                                    <i class="icon-bolt"></i>
                                    {l s='Conectar con Enviamo en 1 Click' mod='enviamo_connector'}
                                </a>
                            </div>

                            <hr style="margin: 30px 0;">

                            {* Opción 2: API Key Manual *}
                            <div>
                                <h4>{l s='Opción 2: Conexión Manual con API Key' mod='enviamo_connector'}</h4>
                                <p class="text-muted">{l s='Si tienes problemas con la conexión automática, usa este método.' mod='enviamo_connector'}</p>

                                <button type="button" class="btn btn-default" onclick="showManualConnectionForm()">
                                    <i class="icon-key"></i>
                                    {l s='Conexión Manual con API Key' mod='enviamo_connector'}
                                </button>

                                {* Formulario manual (oculto por defecto) *}
                                <div id="manual-connection-form" style="display: none; margin-top: 20px;">
                                    <div class="alert alert-info text-left">
                                        <h5><strong>{l s='Pasos para conectar manualmente:' mod='enviamo_connector'}</strong></h5>
                                        <ol>
                                            <li>{l s='Ve a' mod='enviamo_connector'} <a href="https://enviamo.es/dashboard/api-keys" target="_blank">{l s='Enviamo Dashboard → API Keys' mod='enviamo_connector'}</a></li>
                                            <li>{l s='Haz clic en "Generar Nueva API Key"' mod='enviamo_connector'}</li>
                                            <li>{l s='Copia la API Key generada' mod='enviamo_connector'}</li>
                                            <li>{l s='Pégala en el campo de abajo y haz clic en "Conectar"' mod='enviamo_connector'}</li>
                                        </ol>
                                    </div>

                                    <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" class="form-horizontal">
                                        <div class="form-group">
                                            <label class="col-lg-3 control-label">{l s='API Key de Enviamo:' mod='enviamo_connector'}</label>
                                            <div class="col-lg-9">
                                                <input type="text" name="enviamo_api_key" class="form-control" placeholder="env_live_..." required>
                                                <p class="help-block">{l s='Tu API Key comienza con "env_live_" o "env_test_"' mod='enviamo_connector'}</p>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="col-lg-9 col-lg-offset-3">
                                                <button type="submit" name="submit_manual_connection" class="btn btn-success">
                                                    <i class="icon-check"></i>
                                                    {l s='Conectar' mod='enviamo_connector'}
                                                </button>
                                                <button type="button" class="btn btn-default" onclick="hideManualConnectionForm()">
                                                    {l s='Cancelar' mod='enviamo_connector'}
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    {* Características *}
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                <i class="icon-star"></i>
                                {l s='¿Qué obtienes con Enviamo?' mod='enviamo_connector'}
                            </h3>
                        </div>
                        <div class="panel-body">
                            <ul class="list-unstyled" style="font-size: 14px; line-height: 2;">
                                <li><i class="icon-check text-success"></i> {l s='Sincronización automática de pedidos' mod='enviamo_connector'}</li>
                                <li><i class="icon-check text-success"></i> {l s='Generación de etiquetas en 1 click' mod='enviamo_connector'}</li>
                                <li><i class="icon-check text-success"></i> {l s='Soporte multi-transportista (SEUR, GLS, Nacex...)' mod='enviamo_connector'}</li>
                                <li><i class="icon-check text-success"></i> {l s='Actualización automática de estados de envío' mod='enviamo_connector'}</li>
                                <li><i class="icon-check text-success"></i> {l s='Tracking automático para tus clientes' mod='enviamo_connector'}</li>
                                <li><i class="icon-check text-success"></i> {l s='Panel de control centralizado' mod='enviamo_connector'}</li>
                            </ul>
                        </div>
                    </div>

                    <div class="text-center" style="margin-top: 20px;">
                        <p class="text-muted">
                            {l s='¿No tienes cuenta en Enviamo?' mod='enviamo_connector'}
                            <a href="https://enviamo.es/registro" target="_blank">{l s='Regístrate gratis' mod='enviamo_connector'}</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    {/if}
</div>

<style>
#enviamo-connector-config .well {
    background-color: #f9f9f9;
    border: 1px solid #e3e3e3;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

#enviamo-connector-config .btn-group-vertical .btn {
    margin-bottom: 10px;
}

#enviamo-connector-config .badge {
    font-size: 11px;
    padding: 3px 7px;
}
</style>

<script>
function showManualConnectionForm() {
    document.getElementById('manual-connection-form').style.display = 'block';
}

function hideManualConnectionForm() {
    document.getElementById('manual-connection-form').style.display = 'none';
}

function testEnviamoConnection() {
    if (confirm('{l s='¿Probar la conexión con Enviamo?' mod='enviamo_connector'}')) {
        // TODO: Implementar test de conexión AJAX
        alert('{l s='Conexión exitosa con Enviamo!' mod='enviamo_connector'}');
    }
}
</script>
