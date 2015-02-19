{adminheader}
<div class="z-admin-content-pagetitle">
    {icon type="info" size="small"}
    <h3>{gt text='Scripts to execute'}</h3>
</div>

<div>
    <p>{gt text="This module executes PHP scripts, with system core functions and classes available."}</p>
</div>
<form class="z-form" action="{modurl modname="Zwork" type="admin" func="executescrips"}" method="post" enctype="application/x-www-form-urlencoded">
    <div>
    <input type="hidden" name="csrftoken" value="{insert name="csrftoken"}" />
    <fieldset>
        <legend>{gt text='Scripts'}</legend>
        <div class="z-informationmsg">
            {gt text='To add more scripts ready for execution, please upload them in scripts directory:'} {$vars.zwork_scriptsdir|safetext}<br />
            {gt text='See example.php for sample.'}
        </div>
        {foreach from=$scripts key=index item=script}
        <div class="z-formrow">
            <label for="script_{$index}">{$script}</label>
            <input id="script_{$index}" type="checkbox" name="execute[{$index}]" />
            <input id="script_{$index}" type="hidden" name="scripts[{$index}]" value="{$script}" />
        </div>
        {/foreach}
    </fieldset>
    <div class="z-buttons z-formbuttons">
        {button src="button_ok.png" set="icons/extrasmall" __alt="Execute" __title="Execute" __text="Execute"}
        <a href="{modurl modname="Zwork" type="admin" func='modifyconfig'}" title="{gt text="Cancel"}">{img modname=core src="button_cancel.png" set="icons/extrasmall" __alt="Cancel" __title="Cancel"} {gt text="Cancel"}</a>
    </div>
    </div>
</form>
{adminfooter}