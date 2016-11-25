

	</main>

	<!-- footer -->
	<footer id='footer' role="contentinfo">
		<p>
		Powered by <a href='http://processwire.com'>ProcessWire CMS</a>  &nbsp; / &nbsp; 
		<?php 
		if($user->isLoggedin()) {
			// if user is logged in, show a logout link
			echo "<a href='{$config->urls->admin}login/logout/'>Logout ($user->name)</a>";
		} else {
			// if user not logged in, show a login link
			echo "<a href='{$config->urls->admin}'>Admin Login</a>";
		}
		?>
		</p>
	</footer>

</body>
</html>
