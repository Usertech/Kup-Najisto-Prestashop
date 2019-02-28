<style>
	p.payment_module.kupnajisto a 
	{
		padding-left:17px;
	}
</style>
<p class="payment_module kupnajisto">
	<a href="{$link->getModuleLink('kupnajisto', 'payment')|escape:'html'}" title="{l s='Pay with Kup Najisto' mod='kupnajisto'}">
		<img src="{$this_path_bw}kupnajisto.png" alt="{l s='Pay with Kup Najisto' mod='kupnajisto'}" />
		{l s='Pay with Kup Najisto' mod='kupnajisto'}&nbsp;<span>{l s='(what more?)' mod='kupnajisto'}</span>
	</a>
</p>
