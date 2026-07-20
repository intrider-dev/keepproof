# Synthetic receipt fixtures

Every receipt in this directory is generated from invented data. Names, addresses, order numbers, and contact details do not identify real people or businesses.

Regenerate the bitmap and PDF fixtures from the repository root:

```bash
php scripts/generate-receipt-fixtures.php
```

`prompt-injection-receipt.png` deliberately contains hostile instructions. The text is test input, never an instruction to the extractor. Contract tests assert that it cannot change provider configuration, trigger tools, or bypass the confirmation boundary.
