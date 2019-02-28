<h1 class="page-heading">
	{l s='Error' mod='kupnajisto'}
</h1>
<p>
	{l s='An unexpected error has occured. Please try again' mod='kupnajisto'}
</p>
<pre>
	{$error}
</pre>
</br>
</br>
<form action="payment" method="post">
	<button class="button btn btn-default button-medium" type="submit">
		<span><i class="icon-chevron-left left"></i>{l s='Go back' mod='kupnajisto'}</span>
	</button>
</form>
