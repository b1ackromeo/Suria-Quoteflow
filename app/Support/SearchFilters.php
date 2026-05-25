<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SearchFilters
{
    public static function documents(Builder $query, string $term): Builder
    {
        $like = self::like($term);
        $number = self::number($term);
        $date = self::date($term);
        $documentTypes = self::documentTypes($term);
        $statuses = self::statuses($term);
        $paymentTypes = self::paymentTypes($term);
        $acceptedPoReceived = str_contains(self::normalize($term), 'accepted');

        return $query->where(function (Builder $nested) use ($like, $number, $date, $documentTypes, $statuses, $paymentTypes, $acceptedPoReceived) {
            $nested->where('document_number', 'like', $like)
                ->orWhere('external_reference', 'like', $like)
                ->orWhere('project_name', 'like', $like)
                ->orWhere('delivery_to', 'like', $like)
                ->orWhere('currency', 'like', $like)
                ->orWhere('payment_terms_label', 'like', $like)
                ->orWhere('payment_terms_type', 'like', $like)
                ->orWhere('billing_stage_name', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhere('terms', 'like', $like)
                ->orWhereHas('customer', fn (Builder $customer) => self::customers($customer, $like, true))
                ->orWhereHas('supplier', fn (Builder $supplier) => self::suppliers($supplier, $like, true))
                ->orWhereHas('project', fn (Builder $project) => self::projects($project, $like, true))
                ->orWhereHas('relatedDocument', function (Builder $related) use ($like) {
                    $related->where('document_number', 'like', $like)
                        ->orWhere('external_reference', 'like', $like);
                })
                ->orWhereHas('items', function (Builder $item) use ($like) {
                    $item->where('description', 'like', $like)
                        ->orWhere('unit', 'like', $like)
                        ->orWhereHas('product', fn (Builder $product) => self::products($product, $like, true));
                })
                ->orWhereHas('billingStages', function (Builder $stage) use ($like, $number) {
                    $stage->where('stage_name', 'like', $like)
                        ->orWhere('condition_label', 'like', $like)
                        ->orWhere('payment_term', 'like', $like);

                    if ($number !== null) {
                        $stage->orWhere('percentage', $number)
                            ->orWhere('amount', $number)
                            ->orWhere('previously_invoiced', $number)
                            ->orWhere('current_invoice', $number)
                            ->orWhere('remaining_amount', $number);
                    }
                })
                ->orWhereHas('payments', function (Builder $payment) use ($like, $number, $date) {
                    $payment->where('method', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhere('notes', 'like', $like);

                    if ($number !== null) {
                        $payment->orWhere('amount', $number);
                    }

                    if ($date !== null) {
                        $payment->orWhereDate('payment_date', $date);
                    }
                })
                ->orWhereHas('attachments', function (Builder $attachment) use ($like) {
                    $attachment->where('original_name', 'like', $like);
                });

            if ($documentTypes !== []) {
                $nested->orWhereIn('type', $documentTypes);
            }

            if ($statuses !== []) {
                $nested->orWhereIn('status', $statuses);
            }

            if ($acceptedPoReceived) {
                $nested->orWhere(function (Builder $po) {
                    $po->where('type', 'customer_po')
                        ->where('status', 'issued');
                });
            }

            if ($paymentTypes !== []) {
                $nested->orWhereIn('payment_terms_type', $paymentTypes);
            }

            if ($number !== null) {
                $nested->orWhere('payment_due_days', (int) $number)
                    ->orWhere('subtotal', $number)
                    ->orWhere('tax_total', $number)
                    ->orWhere('total', $number);
            }

            if ($date !== null) {
                $nested->orWhereDate('issue_date', $date)
                    ->orWhereDate('due_date', $date);
            }
        });
    }

    public static function customers(Builder $query, string $term, bool $termIsLike = false): Builder
    {
        $like = $termIsLike ? $term : self::like($term);
        $number = $termIsLike ? null : self::number($term);

        return $query->where(function (Builder $nested) use ($like, $number) {
            $nested->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('contact_person', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('billing_address', 'like', $like)
                ->orWhere('shipping_address', 'like', $like)
                ->orWhere('tax_number', 'like', $like);

            if ($number !== null) {
                $nested->orWhere('payment_terms_days', (int) $number);
            }
        });
    }

    public static function suppliers(Builder $query, string $term, bool $termIsLike = false): Builder
    {
        $like = $termIsLike ? $term : self::like($term);
        $number = $termIsLike ? null : self::number($term);

        return $query->where(function (Builder $nested) use ($like, $number) {
            $nested->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('category', 'like', $like)
                ->orWhere('contact_person', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('address', 'like', $like)
                ->orWhere('tax_number', 'like', $like);

            if ($number !== null) {
                $nested->orWhere('payment_terms_days', (int) $number);
            }
        });
    }

    public static function products(Builder $query, string $term, bool $termIsLike = false): Builder
    {
        $like = $termIsLike ? $term : self::like($term);
        $number = $termIsLike ? null : self::number($term);

        return $query->where(function (Builder $nested) use ($like, $number) {
            $nested->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('type', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('unit', 'like', $like);

            if ($number !== null) {
                $nested->orWhere('selling_price', $number)
                    ->orWhere('cost_price', $number)
                    ->orWhere('tax_rate', $number);
            }
        });
    }

    public static function projects(Builder $query, string $term, bool $termIsLike = false): Builder
    {
        $like = $termIsLike ? $term : self::like($term);
        $number = $termIsLike ? null : self::number($term);

        return $query->where(function (Builder $nested) use ($like, $number) {
            $nested->where('project_code', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('status', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('customer', fn (Builder $customer) => self::customers($customer, $like, true))
                ->orWhereHas('manager', function (Builder $manager) use ($like) {
                    $manager->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });

            if ($number !== null) {
                $nested->orWhere('contract_value', $number)
                    ->orWhere('budget_amount', $number)
                    ->orWhere('margin_target_percent', $number);
            }
        });
    }

    private static function like(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)).'%';
    }

    private static function documentTypes(string $term): array
    {
        $needle = self::normalize($term);

        return collect(Document::TYPES)
            ->filter(function (array $meta, string $slug) use ($needle) {
                $aliases = [
                    $slug,
                    $meta['type'],
                    $meta['label'],
                    $meta['singular'],
                    $meta['prefix'] ?? '',
                ];

                if (str_contains($meta['type'], '_po')) {
                    $aliases[] = str_replace('PO', 'Purchase Order', $meta['singular']);
                }

                return collect($aliases)
                    ->map(fn (string $alias) => self::normalize($alias))
                    ->contains(function (string $alias) use ($needle) {
                        if ($alias === '') {
                            return false;
                        }

                        if (strlen($alias) <= 4) {
                            return $needle === $alias;
                        }

                        return str_contains($alias, $needle) || str_contains($needle, $alias);
                    });
            })
            ->pluck('type')
            ->values()
            ->all();
    }

    private static function statuses(string $term): array
    {
        $needle = self::normalize($term);

        return collect(Document::STATUSES)
            ->filter(fn (string $label, string $status) => str_contains(self::normalize($label), $needle) || str_contains(self::normalize($status), $needle))
            ->keys()
            ->values()
            ->all();
    }

    private static function paymentTypes(string $term): array
    {
        $needle = self::normalize($term);
        $matches = [];

        if (str_contains($needle, 'milestone') || str_contains($needle, 'progress') || str_contains($needle, 'stage')) {
            $matches[] = 'milestone';
        }

        if (str_contains($needle, 'standard') || str_contains($needle, 'simple')) {
            $matches[] = 'standard';
        }

        return $matches;
    }

    private static function number(string $term): ?float
    {
        if (! preg_match('/\d[\d,]*(?:\.\d+)?/', $term, $match)) {
            return null;
        }

        return (float) str_replace(',', '', $match[0]);
    }

    private static function date(string $term): ?string
    {
        try {
            if (! preg_match('/\d{4}-\d{2}-\d{2}|\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}/', $term, $match)) {
                return null;
            }

            return Carbon::parse($match[0])->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(['_', '-', '/', '.'], ' ', strtolower($value))));
    }
}
