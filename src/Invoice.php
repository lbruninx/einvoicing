<?php
namespace Einvoicing;

use DateTime;
use Einvoicing\Models\InvoiceTotals;
use Einvoicing\Payments\Payment;
use Einvoicing\Presets\AbstractPreset;
use Einvoicing\Traits\AllowanceOrChargeTrait;
use Einvoicing\Traits\AttachmentsTrait;
use Einvoicing\Traits\BuyerAccountingReferenceTrait;
use Einvoicing\Traits\InvoiceValidationTrait;
use Einvoicing\Traits\PeriodTrait;
use Einvoicing\Traits\PrecedingInvoiceReferencesTrait;
use InvalidArgumentException;
use OutOfBoundsException;
use function array_splice;
use function count;
use function is_subclass_of;
use function round;

class Invoice {
    const DEFAULT_DECIMALS = 8;

    protected $preset = null;
    protected $roundingMatrix = null;
    protected $specification = null;
    protected $businessProcess = null;
    protected $number = null;
    protected $type = 380; // TODO: add constants
    protected $currency = "EUR"; // TODO: add constants
    protected $issueDate = null;
    protected $dueDate = null;
    protected $taxPointDate = null;
    protected $note = null;
    protected $buyerReference = null;
    protected $purchaseOrderReference = null;
    protected $salesOrderReference = null;
    protected $tenderOrLotReference = null;
    protected $contractReference = null;
    protected $paidAmount = 0;
    protected $roundingAmount = 0;
    protected $seller = null;
    protected $buyer = null;
    protected $payee = null;
    protected $delivery = null;
    protected $payment = null;
    protected $lines = [];

    use AllowanceOrChargeTrait;
    use AttachmentsTrait;
    use BuyerAccountingReferenceTrait;
    use PeriodTrait;
    use InvoiceValidationTrait;
    use PrecedingInvoiceReferencesTrait;

    /**
     * Invoice constructor
     * @param string|null $preset Preset classname or NULL for blank invoice
     * @throws InvalidArgumentException if not a valid preset
     */
    public function __construct(?string $preset=null) {
        if ($preset === null) return;

        // Validate preset classname
        if (!is_subclass_of($preset, AbstractPreset::class)) {
            throw new InvalidArgumentException("$preset is not a valid invoice preset");
        }
        /** @var AbstractPreset */
        $this->preset = new $preset();

        // Initialize instance from preset
        $this->setSpecification($this->preset->getSpecification());
        $this->preset->setupInvoice($this);
    }


    /**
     * Get number of decimal places for a given field
     * @param  string $field Field name
     * @return int           Number of decimal places
     */
    public function getDecimals(string $field): int {
        return $this->roundingMatrix[$field] ?? $this->roundingMatrix[''] ?? self::DEFAULT_DECIMALS;
    }


    /**
     * Set rounding matrix
     * @param  array $matrix Rounding matrix
     * @return self          Invoice instance
     */
    public function setRoundingMatrix(array $matrix): self {
        $this->roundingMatrix = $matrix;
        return $this;
    }


    /**
     * Get specification identifier
     * @return string|null Specification identifier
     */
    public function getSpecification(): ?string {
        return $this->specification;
    }


    /**
     * Set specification identifier
     * @param  string $specification Specification identifier
     * @return self                  Invoice instance
     */
    public function setSpecification(string $specification): self {
        $this->specification = $specification;
        return $this;
    }


    /**
     * Get business process type
     * @return string|null Business process type
     */
    public function getBusinessProcess(): ?string {
        return $this->businessProcess;
    }


    /**
     * Set business process type
     * @param  string|null $businessProcess Business process type
     * @return self                         Invoice instance
     */
    public function setBusinessProcess(?string $businessProcess): self {
        $this->businessProcess = $businessProcess;
        return $this;
    }


    /**
     * Get invoice number
     * @return string|null Invoice number
     */
    public function getNumber(): ?string {
        return $this->number;
    }


    /**
     * Set invoice number
     * @param  string $number Invoice number
     * @return self           Invoice instance
     */
    public function setNumber(string $number): self {
        $this->number = $number;
        return $this;
    }


    /**
     * Get invoice type code
     * @return int Invoice type code
     */
    public function getType(): int {
        return $this->type;
    }


    /**
     * Set invoice type code
     * @param  int  $typeCode Invoice type code
     * @return self           Invoice instance
     */
    public function setType(int $typeCode): self {
        $this->type = $typeCode;
        return $this;
    }


    /**
     * Get document currency code
     * @return string Document currency code
     */
    public function getCurrency(): string {
        return $this->currency;
    }


    /**
     * Set document currency code
     * @param  string $currencyCode Document currency code
     * @return self                 Invoice instance
     */
    public function setCurrency(string $currencyCode): self {
        $this->currency = $currencyCode;
        return $this;
    }


    /**
     * Get invoice issue date
     * @return DateTime|null Invoice issue date
     */
    public function getIssueDate(): ?DateTime {
        return $this->issueDate;
    }


    /**
     * Set invoice issue date
     * @param  DateTime $issueDate Invoice issue date
     * @return self                Invoice instance
     */
    public function setIssueDate(DateTime $issueDate): self {
        $this->issueDate = $issueDate;
        return $this;
    }


    /**
     * Get payment due date
     * @return DateTime|null Payment due date
     */
    public function getDueDate(): ?DateTime {
        return $this->dueDate;
    }


    /**
     * Set payment due date
     * @param  DateTime|null $dueDate Payment due date
     * @return self                   Invoice instance
     */
    public function setDueDate(?DateTime $dueDate): self {
        $this->dueDate = $dueDate;
        return $this;
    }


    /**
     * Get tax point date
     * @return DateTime|null Tax point date
     */
    public function getTaxPointDate(): ?DateTime {
        return $this->taxPointDate;
    }


    /**
     * Set tax point date
     * @param  DateTime|null $taxPointDate Tax point date
     * @return self                        Invoice instance
     */
    public function setTaxPointDate(?DateTime $taxPointDate): self {
        $this->taxPointDate = $taxPointDate;
        return $this;
    }


    /**
     * Get invoice note
     * @return string|null Invoice note
     */
    public function getNote(): ?string {
        return $this->note;
    }


    /**
     * Set invoice note
     * @param  string|null $note Invoice note
     * @return self              Invoice instance
     */
    public function setNote(?string $note): self {
        $this->note = $note;
        return $this;
    }


    /**
     * Get buyer reference
     * @return string|null Buyer reference
     */
    public function getBuyerReference(): ?string {
        return $this->buyerReference;
    }


    /**
     * Set buyer reference
     * @param  string|null $buyerReference Buyer reference
     * @return self                        Invoice instance
     */
    public function setBuyerReference(?string $buyerReference): self {
        $this->buyerReference = $buyerReference;
        return $this;
    }


    /**
     * Get purchase order reference
     * @return string|null Purchase order reference
     */
    public function getPurchaseOrderReference(): ?string {
        return $this->purchaseOrderReference;
    }


    /**
     * Set purchase order reference
     * @param  string|null $purchaseOrderReference Purchase order reference
     * @return self                                Invoice instance
     */
    public function setPurchaseOrderReference(?string $purchaseOrderReference): self {
        $this->purchaseOrderReference = $purchaseOrderReference;
        return $this;
    }


    /**
     * Get sales order reference
     * @return string|null Sales order reference
     */
    public function getSalesOrderReference(): ?string {
        return $this->salesOrderReference;
    }


    /**
     * Set sales order reference
     * @param  string|null $salesOrderReference Sales order reference
     * @return self                             Invoice instance
     */
    public function setSalesOrderReference(?string $salesOrderReference): self {
        $this->salesOrderReference = $salesOrderReference;
        return $this;
    }


    /**
     * Get tender or lot reference
     * @return string|null Tender or lot reference
     */
    public function getTenderOrLotReference(): ?string {
        return $this->tenderOrLotReference;
    }


    /**
     * Set tender or lot reference
     * @param  string|null $tenderOrLotReference Tender or lot reference
     * @return self                              Invoice instance
     */
    public function setTenderOrLotReference(?string $tenderOrLotReference): self {
        $this->tenderOrLotReference = $tenderOrLotReference;
        return $this;
    }


    /**
     * Get contract reference
     * @return string|null Contract reference
     */
    public function getContractReference(): ?string {
        return $this->contractReference;
    }


    /**
     * Set contract reference
     * @param  string|null $contractReference Contract reference
     * @return self                           Invoice instance
     */
    public function setContractReference(?string $contractReference): self {
        $this->contractReference = $contractReference;
        return $this;
    }


    /**
     * Get invoice prepaid amount
     * NOTE: may be rounded according to the CIUS specification
     * @return float Invoice prepaid amount
     */
    public function getPaidAmount(): float {
        return round($this->paidAmount, $this->getDecimals('invoice/paidAmount'));
    }


    /**
     * Set invoice prepaid amount
     * @param  float $paidAmount Invoice prepaid amount
     * @return self              Invoice instance
     */
    public function setPaidAmount(float $paidAmount): self {
        $this->paidAmount = $paidAmount;
        return $this;
    }


    /**
     * Get invoice rounding amount
     * NOTE: may be rounded according to the CIUS specification
     * @return float Invoice rounding amount
     */
    public function getRoundingAmount(): float {
        return round($this->roundingAmount, $this->getDecimals('invoice/roundingAmount'));
    }


    /**
     * Set invoice rounding amount
     * @param  float $roundingAmount Invoice rounding amount
     * @return self                  Invoice instance
     */
    public function setRoundingAmount(float $roundingAmount): self {
        $this->roundingAmount = $roundingAmount;
        return $this;
    }


    /**
     * Get seller
     * @return Party|null Seller instance
     */
    public function getSeller(): ?Party {
        return $this->seller;
    }


    /**
     * Set seller
     * @param  Party $seller Seller instance
     * @return self          Invoice instance
     */
    public function setSeller(Party $seller): self {
        $this->seller = $seller;
        return $this;
    }


    /**
     * Get buyer
     * @return Party|null Buyer instance
     */
    public function getBuyer(): ?Party {
        return $this->buyer;
    }


    /**
     * Set buyer
     * @param  Party $buyer Buyer instance
     * @return self          Invoice instance
     */
    public function setBuyer(Party $buyer): self {
        $this->buyer = $buyer;
        return $this;
    }


    /**
     * Get payee
     * @return Party|null Payee instance
     */
    public function getPayee(): ?Party {
        return $this->payee;
    }


    /**
     * Set payee
     * @param  Party|null $payee Payee instance
     * @return self              Invoice instance
     */
    public function setPayee(?Party $payee): self {
        $this->payee = $payee;
        return $this;
    }


    /**
     * Get delivery information
     * @return Delivery|null Delivery instance
     */
    public function getDelivery(): ?Delivery {
        return $this->delivery;
    }


    /**
     * Set delivery information
     * @param  Delivery|null $delivery Delivery instance
     * @return self                    Invoice instance
     */
    public function setDelivery(?Delivery $delivery): self {
        $this->delivery = $delivery;
        return $this;
    }


    /**
     * Get payment information
     * @return Payment|null Payment instance
     */
    public function getPayment(): ?Payment {
        return $this->payment;
    }


    /**
     * Set payment information
     * @param  Payment|null $payment Payment instance
     * @return self                  Invoice instance
     */
    public function setPayment(?Payment $payment): self {
        $this->payment = $payment;
        return $this;
    }


    /**
     * Get invoice lines
     * @return InvoiceLine[] Invoice lines
     */
    public function getLines(): array {
        return $this->lines;
    }


    /**
     * Add invoice line
     * @param  InvoiceLine $line Invoice line instance
     * @return self              Invoice instance
     */
    public function addLine(InvoiceLine $line): self {
        $this->lines[] = $line;
        return $this;
    }


    /**
     * Remove invoice line
     * @param  int  $index Invoice line index
     * @return self        Invoice instance
     * @throws OutOfBoundsException if line index is out of bounds
     */
    public function removeLine(int $index): self {
        if ($index < 0 || $index >= count($this->lines)) {
            throw new OutOfBoundsException('Could not find line by index inside invoice');
        }
        array_splice($this->lines, $index, 1);
        return $this;
    }


    /**
     * Clear all invoice lines
     * @return self Invoice instance
     */
    public function clearLines(): self {
        $this->lines = [];
        return $this;
    }


    /**
     * Get invoice total
     * @return InvoiceTotals Invoice totals
     */
    public function getTotals(): InvoiceTotals {
        return InvoiceTotals::fromInvoice($this);
    }
}
