{include file="findInclude:common/templates/header.tpl"}

{include file="findInclude:modules/stats/templates/interval-options.tpl"}

{foreach $charts as $chartParams}
<div class="focal">
{drawChart chartParams=$chartParams}
</div>
{/foreach}
{include file="findInclude:modules/stats/templates/service-options.tpl"}
{include file="findInclude:common/templates/footer.tpl"}
