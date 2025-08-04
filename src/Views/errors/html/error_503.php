<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow">
	<title><?= lang('Maintenance.serverDowTitle'); ?></title>

	<style>
	:root {
		--primary-color: #4f46e5;
		--secondary-color: #6b7280;
		--background-color: #f8fafc;
		--card-background: #ffffff;
		--text-primary: #1f2937;
		--text-secondary: #6b7280;
		--border-color: #e5e7eb;
		--shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
		--gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	}

	@media (prefers-color-scheme: dark) {
		:root {
			--background-color: #111827;
			--card-background: #1f2937;
			--text-primary: #f9fafb;
			--text-secondary: #d1d5db;
			--border-color: #374151;
			--shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
		}
	}

	* {
		margin: 0;
		padding: 0;
		box-sizing: border-box;
	}

	body {
		min-height: 100vh;
		background: var(--background-color);
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
		color: var(--text-primary);
		line-height: 1.6;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 1rem;
	}

	.maintenance-container {
		max-width: 600px;
		width: 100%;
		background: var(--card-background);
		border-radius: 16px;
		box-shadow: var(--shadow);
		overflow: hidden;
		border: 1px solid var(--border-color);
	}

	.maintenance-header {
		background: var(--gradient);
		padding: 2rem;
		text-align: center;
		color: white;
	}

	.maintenance-icon {
		width: 80px;
		height: 80px;
		margin: 0 auto 1rem;
		background: rgba(255, 255, 255, 0.2);
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 2rem;
	}

	.maintenance-header h1 {
		font-size: 1.75rem;
		font-weight: 700;
		margin-bottom: 0.5rem;
		letter-spacing: -0.025em;
	}

	.maintenance-header p {
		font-size: 1rem;
		opacity: 0.9;
		font-weight: 400;
	}

	.maintenance-content {
		padding: 2rem;
	}

	.maintenance-message {
		font-size: 1.125rem;
		color: var(--text-secondary);
		text-align: center;
		margin-bottom: 2rem;
		line-height: 1.7;
	}

	.custom-message {
		background: #f0f9ff;
		border: 1px solid #bae6fd;
		border-radius: 8px;
		padding: 1.5rem;
		margin-bottom: 2rem;
		color: #0c4a6e;
		text-align: center;
		font-weight: 500;
	}

	@media (prefers-color-scheme: dark) {
		.custom-message {
			background: #1e3a8a;
			border-color: #3b82f6;
			color: #bfdbfe;
		}
	}

	.maintenance-features {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
		gap: 1rem;
		margin-bottom: 2rem;
	}

	.feature {
		text-align: center;
		padding: 1rem;
		background: var(--background-color);
		border-radius: 8px;
		border: 1px solid var(--border-color);
	}

	.feature-icon {
		font-size: 1.5rem;
		margin-bottom: 0.5rem;
		display: block;
	}

	.feature-text {
		font-size: 0.875rem;
		color: var(--text-secondary);
		font-weight: 500;
	}

	.maintenance-footer {
		text-align: center;
		padding-top: 1rem;
		border-top: 1px solid var(--border-color);
		color: var(--text-secondary);
		font-size: 0.875rem;
	}

	.refresh-btn {
		display: inline-flex;
		align-items: center;
		gap: 0.5rem;
		background: var(--primary-color);
		color: white;
		border: none;
		padding: 0.75rem 1.5rem;
		border-radius: 8px;
		font-size: 1rem;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.2s ease;
		text-decoration: none;
		margin-top: 1rem;
	}

	.refresh-btn:hover {
		background: #3730a3;
		transform: translateY(-1px);
	}

	.status-indicator {
		display: inline-flex;
		align-items: center;
		gap: 0.5rem;
		background: #fef3c7;
		color: #92400e;
		padding: 0.5rem 1rem;
		border-radius: 20px;
		font-size: 0.875rem;
		font-weight: 500;
		margin-bottom: 1rem;
	}

	@media (prefers-color-scheme: dark) {
		.status-indicator {
			background: #451a03;
			color: #fbbf24;
		}
	}

	.pulse {
		width: 8px;
		height: 8px;
		background: #f59e0b;
		border-radius: 50%;
		animation: pulse 2s infinite;
	}

	@keyframes pulse {
		0%, 100% { opacity: 1; }
		50% { opacity: 0.4; }
	}

	@media (max-width: 640px) {
		.maintenance-header {
			padding: 1.5rem;
		}
		
		.maintenance-content {
			padding: 1.5rem;
		}
		
		.maintenance-header h1 {
			font-size: 1.5rem;
		}
		
		.maintenance-features {
			grid-template-columns: 1fr;
		}
	}

	/* Accessibility improvements */
	@media (prefers-reduced-motion: reduce) {
		.pulse {
			animation: none;
		}
		
		.refresh-btn:hover {
			transform: none;
		}
	}
	</style>
</head>
<body>
	<div class="maintenance-container">
		<div class="maintenance-header">
			<div class="maintenance-icon">
				🔧
			</div>
			<h1><?= lang('Maintenance.serverDowTitle'); ?></h1>
			<p>Temporary Service Interruption</p>
		</div>

		<div class="maintenance-content">
			<div class="status-indicator">
				<div class="pulse"></div>
				Maintenance in Progress
			</div>

			<div class="maintenance-message">
				<?= lang('Maintenance.serverDowMessage'); ?>
			</div>

			<?php if (! empty($message) && $message !== '(null)') : ?>
				<div class="custom-message">
					<?= esc($message) ?>
				</div>
			<?php endif ?>

			<div class="maintenance-features">
				<div class="feature">
					<span class="feature-icon">⚡</span>
					<div class="feature-text">Performance Upgrades</div>
				</div>
				<div class="feature">
					<span class="feature-icon">🔒</span>
					<div class="feature-text">Security Updates</div>
				</div>
				<div class="feature">
					<span class="feature-icon">🚀</span>
					<div class="feature-text">New Features</div>
				</div>
			</div>

			<div class="maintenance-footer">
				<button class="refresh-btn" onclick="window.location.reload()">
					🔄 Check Again
				</button>
				<p style="margin-top: 1rem;">
					We appreciate your patience while we improve our service.
				</p>
			</div>
		</div>
	</div>

	<script>
		// Auto-refresh every 30 seconds
		setTimeout(() => {
			window.location.reload();
		}, 30000);

		// Keyboard accessibility
		document.addEventListener('keydown', (e) => {
			if (e.key === 'r' || e.key === 'R') {
				window.location.reload();
			}
		});
	</script>
</body>
</html>