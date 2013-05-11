{**
 * plugins/importexport/jats/index.tpl
 *
 * List of operations this plugin can perform
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.jats.displayName"}
{include file="common/header.tpl"}
{/strip}
<br/>

<h3>{translate key="plugins.importexport.jats.export"}</h3>
<ul class="plain">
  <li>&#187; <a href="{plugin_url path="issues"}">{translate key="plugins.importexport.jats.export.issues"}</a></li>
  <li>&#187; <a href="{plugin_url path="articles"}">{translate key="plugins.importexport.jats.export.articles"}</a></li>
</ul>

<h3>{translate key="plugins.importexport.jats.import"}</h3>
<p>{translate key="plugins.importexport.jats.import.description"}</p>

{include file="common/footer.tpl"}
