{capture name=path}
	<a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='kupnajisto'}">{l s='Checkout' mod='kupnajisto'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Pay with Kup Najisto' mod='kupnajisto'}
{/capture}

<h1 class="page-heading">
	{l s='Order summary' mod='kupnajisto'}
</h1>


{if $num_products <= 0}
	<p class="alert alert-warning">
		{l s='Your shopping cart is empty.' mod='kupnajisto'}
	</p>
{elseif $total >= 1000000}
	<p class="alert alert-warning">
		{l s='Your order exceeds the maximum price supported by Kup Najisto.' mod='kupnajisto'}
	</p>
{else}
	<form action="{$link->getModuleLink('kupnajisto', 'validation')|escape:'html':'UTF-8'}" method="post">
		<div class="box cheque-box">
			<img src="{$this_path_bw}kupnajisto.png" alt="{l s='Pay with Kup Najisto' mod='kupnajisto'}" />
			<h3 class="page-subheading"></h3>
			<p class="cheque-indent">
				<p id="warning" class="alert alert-warning" hidden>						
				</p>
				{literal}
				<script type="text/javascript">
					function Validate()
					{
						var re = /^0{2}[0-9]{12}$|^[0-9]{9}$|^\+[0-9]{12}$/;
						var phone = document.getElementById("phone_input").value;
					  	if (!phone.match(re))
					  	{
							document.getElementById("warning").innerHTML = "Invalid phone number";
							document.getElementById("warning").removeAttribute("hidden");
					  		return false;
					  	}
					  		
					}
				</script>
				{/literal}
				{if $phone_status == 1}{* phone not set *}
					<p>
						{l s='We need a telephone number:' mod='kupnajisto'}
						<input id="phone_input" type="text" name="phone_input"/>
					</p>					
				{elseif $phone_status == 2}{* phone invalid *}
					<p>
						{l s='Your phone is not valid. Please enter it with one of the following formats:' mod='kupnajisto'}</br>
						{l s='● 900123456 (9 digits number)' mod='kupnajisto'}</br>
						{l s='● 00420900123456 (14 digits number. starts with 00)' mod='kupnajisto'}</br>
						{l s='● +420900123456 (12 digits number. starts with plus “+”)' mod='kupnajisto'}</br>
						<input id="phone_input" type="text" name="phone_input" value="{$phone}"/>
					</p>
				{else}
					<input id="phone_input" type="text" name="phone_input" value="{$phone}" hidden/>
				{/if}

				</br><p>{l s='DESCRIPTION OF THE ORDER HERE' mod='kupnajisto'}</p>
			</p>
		</div>
		<p class="cart_navigation clearfix" id="cart_navigation">
			<a class="button-exclusive btn btn-default" href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}">
				<i class="icon-chevron-left"></i>{l s='Other payment methods' mod='kupnajisto'}
			</a>
			<button class="button btn btn-default button-medium" type="submit" onclick="return Validate();">
				<span>{l s='I confirm my order' mod='kupnajisto'}<i class="icon-chevron-right right"></i></span>
			</button>
		</p>
	</form>
{/if}
