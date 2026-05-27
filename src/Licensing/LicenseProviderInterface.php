<?php

declare(strict_types=1);

namespace Jcore\Update\Licensing;

interface LicenseProviderInterface {

	public function getLicenseKey(): ?string;
}
