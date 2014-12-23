<!DOCTYPE html>
<html>
	<head>
		<title>PHP simple group mail</title>
		<link href="//fonts.googleapis.com/css?family=PT+Sans:400,700,400italic" rel="stylesheet" type="text/css">
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap-theme.min.css">
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>

		<style type="text/css">
		.control-label { text-align:left ! important; width: 200px; }
		.adresslist > div  { margin-bottom:5px; }
		</style>
	</head>
	<body>
		<?php
			if(count($log) > 0) {
				echo '<div class="log" style="background:#ffaaaa">';
				foreach($log as $l) {
					printf("<p>%s</p>\n", htmlspecialchars($l));
				}
				echo '</div>';
			}
		?>

		<div class="container">
			<h2>PHP simple group mail</h2>
			<br />
			<br />

			<form class="form-horizontal" method="post">
			<?php
				$i = 1;
				foreach($data as $d) {
			?>
				<div class="container">
					<fieldset>
						<legend><?php out($d['identifier']) ?></legend>

						<div class="form-group">
							<label class="col-md-4 control-label" for="identifier<?php out($i) ?>">List address</label>  
							<div class="col-md-4">
								<input id="identifier<?php out($i) ?>" type="email" class="form-control input-md" value="<?php out($d['identifier']) ?>" name="identifier<?php out($i) ?>">
							</div>
						</div>

						<div class="form-group">
							<label class="col-md-4 control-label" for="host<?php out($i) ?>">Host</label>  
							<div class="col-md-4">
								<input id="host<?php out($i) ?>" type="text" placeholder="" class="form-control input-md" value="<?php out($d['host']) ?>" name="host<?php out($i) ?>">
							</div>
						</div>

						<div class="form-group">
							<label class="col-md-4 control-label" for="user<?php out($i) ?>">User</label>  
							<div class="col-md-4">
								<input id="user<?php out($i) ?>" type="text" placeholder="" class="form-control input-md" value="<?php out($d['user']) ?>" name="user<?php out($i) ?>">
							</div>
						</div>

						<div class="form-group">
							<label class="col-md-4 control-label" for="pass<?php out($i) ?>">Pass</label>  
							<div class="col-md-4">
								<input id="pass<?php out($i) ?>" type="text" placeholder="" class="form-control input-md" value="<?php out($d['pass']) ?>" name="pass<?php out($i) ?>">
							</div>
						</div>

						<div class="form-group">
							<label class="col-md-4 control-label" for="secret<?php out($i) ?>">Secret</label>
							<div class="col-md-4">
								<input id="secret<?php out($i) ?>" type="text" placeholder="" class="form-control input-md" value="<?php out($d['secret']) ?>" name="secret<?php out($i) ?>">
							</div>
						</div>

						<div class="form-group">
							<label class="col-md-4 control-label">Recipients</label>
							<div class="col-md-4 adresslist">
								<textarea name="recipients<?php out($i) ?>"><?php
									foreach($d['recipients'] as $recipient) {
										printf("%s\n", $recipient);
									}
								?></textarea>
							</div>
						</div>

						<div class="form-group">
							<label class="col-md-4 control-label">Delete</label>
							<div class="col-md-4">
								<label class="checkbox-inline" for="delete<?php out($i) ?>">
									<input type="checkbox" name="delete<?php out($i) ?>" id="delete<?php out($i) ?>">
									Delete this instance
								</label>
							</div>
						</div>
					</fieldset>
					<br />
					<br />
				</div>
				<?php
					$i++;
				}
			?>

			<div class="container">
				<fieldset>
					<legend>Add a new mail group instance</legend>

					<div class="form-group">
						<label class="col-md-4 control-label" for="identifier<?php out($i) ?>">List address</label>
						<div class="col-md-4">
							<input id="identifier<?php out($i) ?>" name="identifier<?php out($i) ?>" type="email" class="form-control input-md">
						</div>
					</div>

					<div class="form-group">
						<label class="col-md-4 control-label" for="host<?php out($i) ?>">Host</label>
						<div class="col-md-4">
							<input id="host<?php out($i) ?>" name="host<?php out($i) ?>" type="text" placeholder="" class="form-control input-md">
						</div>
					</div>

					<div class="form-group">
						<label class="col-md-4 control-label" for="user<?php out($i) ?>">User</label>
						<div class="col-md-4">
							<input id="user<?php out($i) ?>" name="user<?php out($i) ?>" type="text" placeholder="" class="form-control input-md">
						</div>
					</div>

					<div class="form-group">
						<label class="col-md-4 control-label" for="pass<?php out($i) ?>">Pass</label>
						<div class="col-md-4">
							<input id="pass<?php out($i) ?>" name="pass<?php out($i) ?>" type="text" placeholder="" class="form-control input-md">
						</div>
					</div>

					<div class="form-group">
						<label class="col-md-4 control-label" for="secret<?php out($i) ?>">Secret</label>
						<div class="col-md-4">
							<input id="secret<?php out($i) ?>" name="secret<?php out($i) ?>" type="text" placeholder="" class="form-control input-md">
						</div>
					</div>

					<div class="form-group">
						<label class="col-md-4 control-label">Recipients</label>
						<div class="col-md-4 adresslist">
							<textarea name="recipients<?php out($i) ?>"></textarea>
						</div>
					</div>
				</fieldset>
				<br />
				<br />
			</div>

			<button type="submit" class="btn btn-primary">Save</button>

			<input type="hidden" name="stanzas" value="<?php out($i) ?>">
			</form>
		</div>
	</body>
</html>

