<?php

namespace TomatoPHP\FilamentEcommerce\Filament\Resources;

use App\Models\User;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use TomatoPHP\FilamentAccounts\Components\AccountColumn;
use TomatoPHP\FilamentAccounts\Models\Account;
use TomatoPHP\FilamentEcommerce\Filament\Resources\OrderResource\Pages;
use TomatoPHP\FilamentEcommerce\Filament\Resources\OrderResource\RelationManagers;
use TomatoPHP\FilamentEcommerce\Models\Branch;
use TomatoPHP\FilamentEcommerce\Models\Company;
use TomatoPHP\FilamentEcommerce\Models\Delivery;
use TomatoPHP\FilamentEcommerce\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use TomatoPHP\FilamentEcommerce\Models\OrderLog;
use TomatoPHP\FilamentEcommerce\Models\Product;
use TomatoPHP\FilamentEcommerce\Models\ShippingPrice;
use TomatoPHP\FilamentEcommerce\Models\ShippingVendor;
use TomatoPHP\FilamentLocations\Models\City;
use TomatoPHP\FilamentLocations\Models\Country;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-cursor-arrow-rays';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return trans('filament-ecommerce::messages.group'); // TODO: Change the autogenerated stub
    }

    public static function getNavigationLabel(): string
    {
        return trans('filament-ecommerce::messages.orders.title'); // TODO: Change the autogenerated stub
    }

    public static function getLabel(): ?string
    {
        return trans('filament-ecommerce::messages.orders.single'); // TODO: Change the autogenerated stub
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('uuid')
                    ->disabled(fn(Order $order)=> $order->exists)
                    ->label(trans('filament-ecommerce::messages.orders.columns.uuid'))
                    ->default(fn () => (string) \Illuminate\Support\Str::uuid())
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Forms\Components\Grid::make([
                    'sm' => 1,
                    'lg' => 12,
                ])->schema([
                    Forms\Components\Section::make('Company')->schema([
                        Forms\Components\Select::make('company_id')
                            ->searchable()
                            ->options(Company::query()->pluck('name', 'id')->toArray())
                            ->preload()
                            ->live()
                            ->required()
                            ->label(trans('filament-ecommerce::messages.orders.columns.company_id')),
                        Forms\Components\Select::make('branch_id')
                            ->searchable()
                            ->required()
                            ->options(fn(Forms\Get $get) => Branch::query()->where('company_id', $get('company_id'))->pluck('name', 'id')->toArray())
                            ->label(trans('filament-ecommerce::messages.orders.columns.branch_id')),
                        Forms\Components\Select::make('status')
                            ->label(trans('filament-ecommerce::messages.orders.columns.status'))
                            ->searchable()
                            ->preload()
                            ->options([
                                'pending' => 'Pending',
                                'prepear' => 'Prepear',
                                'withdrew' => 'Withdrew',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'part-delivered' => 'Part Delivered',
                                'returned' => 'Returned',
                                'canceled' => 'Canceled',
                            ])
                            ->required()
                            ->default('pending'),
                        Forms\Components\Select::make('payment_method')
                            ->searchable()
                            ->preload()
                            ->options([
                                'cash' => 'Cash',
                                'credit' => 'Credit',
                                'wallet' => 'Wallet',
                            ])
                            ->default('cash')
                            ->label(trans('filament-ecommerce::messages.orders.columns.payment_method')),
                    ])->columns(2),
                    Forms\Components\Section::make('Account')->schema([
                        Forms\Components\Select::make('account_id')
                            ->searchable()
                            ->options( \App\Models\Account::query()->where('is_active', 1)->pluck('name', 'id')->toArray())
                            ->lazy()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                $account = \App\Models\Account::find($get('account_id'));
                                if($account){
                                    $set('name', $account->name);
                                    $set('phone', $account->phone);
                                }
                            })
                            ->label(trans('filament-ecommerce::messages.orders.columns.account_id'))
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label(trans('filament-ecommerce::messages.orders.columns.name'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label(trans('filament-ecommerce::messages.orders.columns.phone'))
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\Select::make('source')
                            ->searchable()
                            ->options([
                                'system' => 'System',
                                'web' => 'Website',
                                'app' => 'Mobile App',
                                'phone' => 'Callcenter',
                                'pos' => 'POS',
                                'other' => 'Other',
                            ])
                            ->label(trans('filament-ecommerce::messages.orders.columns.source'))
                            ->required()
                            ->default('system'),
                    ])->columnSpan(6),
                    Forms\Components\Section::make('Location')->schema([
                        Forms\Components\Select::make('country_id')
                            ->preload()
                            ->searchable()
                            ->live()
                            ->options(Country::query()->pluck('name', 'id')->toArray())
                            ->label(trans('filament-ecommerce::messages.orders.columns.country_id'))
                            ->columnSpanFull(),
                        Forms\Components\Select::make('city_id')
                            ->searchable()
                            ->live()
                            ->options(fn(Forms\Get $get) => City::where('country_id', $get('country_id'))->pluck('name', 'id')->toArray())
                            ->label(trans('filament-ecommerce::messages.orders.columns.city_id')),
                        Forms\Components\Select::make('area_id')
                            ->searchable()
                            ->options(fn(Forms\Get $get) => \TomatoPHP\FilamentLocations\Models\Area::where('city_id', $get('city_id'))->pluck('name', 'id')->toArray())
                            ->label(trans('filament-ecommerce::messages.orders.columns.area_id')),
                        Forms\Components\TextInput::make('flat')
                            ->label(trans('filament-ecommerce::messages.orders.columns.flat'))
                            ->columnSpanFull()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('address')
                            ->label(trans('filament-ecommerce::messages.orders.columns.address'))
                            ->columnSpanFull(),
                    ])->columns(2)->columnSpan(6),
                ]),
                Forms\Components\Section::make('Items')
                    ->schema([
                    Forms\Components\Repeater::make('items')
                        ->hiddenLabel()
                        ->label(trans('filament-ecommerce::messages.orders.columns.items'))
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->searchable()
                                ->options(Product::query()->where('is_activated', 1)->pluck('name', 'id')->toArray())
                                ->live()
                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                    $product = Product::find($get('product_id'));
                                    if($product){
                                        $discount = 0;
                                        if($product->discount_to && Carbon::parse($product->discount_to)->isFuture()){
                                            $discount = $product->discount;
                                        }

                                        $set('price', $product->price);
                                        $set('discount', $discount);
                                        $set('vat', $product->vat);
                                        $set('total', (($product->price+$product->vat) - $discount)*$get('qty'));
                                    }
                                })
                                ->label(trans('filament-ecommerce::messages.orders.columns.product_id'))
                                ->columnSpan(3),
                            Forms\Components\TextInput::make('qty')
                                ->live()
                                ->label(trans('filament-ecommerce::messages.orders.columns.qty'))
                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                    $product = Product::find($get('product_id'));
                                    if($product){
                                        $discount = 0;
                                        if($product->discount_to && Carbon::parse($product->discount_to)->isFuture()){
                                            $discount = $product->discount;
                                        }

                                        $set('price', $product->price);
                                        $set('discount', $discount);
                                        $set('vat', $product->vat);
                                        $set('total', (($product->price+$product->vat) - $discount)*$get('qty'));
                                    }
                                })
                                ->default(1)
                                ->numeric(),
                            Forms\Components\TextInput::make('price')
                                ->disabled()
                                ->label(trans('filament-ecommerce::messages.orders.columns.price'))
                                ->columnSpan(2)
                                ->default(0)
                                ->numeric(),
                            Forms\Components\TextInput::make('discount')
                                ->disabled()
                                ->label(trans('filament-ecommerce::messages.orders.columns.discount'))
                                ->columnSpan(2)
                                ->default(0)
                                ->numeric(),
                            Forms\Components\TextInput::make('vat')
                                ->disabled()
                                ->label(trans('filament-ecommerce::messages.orders.columns.vat'))
                                ->columnSpan(2)
                                ->default(0)
                                ->numeric(),
                            Forms\Components\TextInput::make('total')
                                ->disabled()
                                ->label(trans('filament-ecommerce::messages.orders.columns.total'))
                                ->columnSpan(2)
                                ->default(0)
                                ->numeric(),
                        ])
                        ->lazy()
                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                            $items = $get('items');
                            $total = 0;
                            $discount = 0;
                            $vat = 0;
                            foreach ($items as $orderItem){
                                $product = Product::find($orderItem['product_id']);
                                if($product){
                                    $getDiscount= 0;
                                    if($product->discount_to && Carbon::parse($product->discount_to)->isFuture()){
                                        $getDiscount = $product->discount;
                                    }

                                    $total += ((($product->price+$product->vat)-$getDiscount)*$orderItem['qty']);
                                    $discount += ($getDiscount*$orderItem['qty']);
                                    $vat +=  ($product->vat*$orderItem['qty']);
                                }


                            }
                            $set('total', $total);
                            $set('discount', $discount);
                            $set('vat', $vat);
                        })
                        ->columns(12)
                ]),
                Forms\Components\Section::make('Totals')->schema([
//                    Forms\Components\TextInput::make('coupon_id')
//                        ->numeric(),
                    Forms\Components\TextInput::make('shipping')
                        ->lazy()
                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                            $items = $get('items');
                            $total = 0;
                            foreach ($items as $orderItem){
                                $product = Product::find($orderItem['product_id']);
                                if($product){
                                    $getDiscount= 0;
                                    if($product->discount_to && Carbon::parse($product->discount_to)->isFuture()){
                                        $getDiscount = $product->discount;
                                    }

                                    $total += ((($product->price+$product->vat)-$getDiscount)*$orderItem['qty']);
                                }


                            }

                            $set('total', $total+(int)$get('shipping'));
                        })
                        ->label(trans('filament-ecommerce::messages.orders.columns.shipping'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('vat')
                        ->disabled()
                        ->label(trans('filament-ecommerce::messages.orders.columns.vat'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('discount')
                        ->disabled()
                        ->label(trans('filament-ecommerce::messages.orders.columns.discount'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('total')
                        ->disabled()
                        ->label(trans('filament-ecommerce::messages.orders.columns.total'))
                        ->numeric()
                        ->default(0),

                    Forms\Components\Toggle::make('has_returns')
                        ->label(trans('filament-ecommerce::messages.orders.columns.has_returns'))
                        ->live(),

                    Forms\Components\TextInput::make('return_total')
                        ->label(trans('filament-ecommerce::messages.orders.columns.return_total'))
                        ->hidden(fn (Forms\Get $get) => !($get('has_returns')))
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('reason')
                        ->label(trans('filament-ecommerce::messages.orders.columns.reason'))
                        ->hidden(fn (Forms\Get $get) => !($get('has_returns')))
                        ->maxLength(255),
                    Forms\Components\Textarea::make('notes')
                        ->label(trans('filament-ecommerce::messages.orders.columns.notes'))
                        ->columnSpanFull(),
                ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                AccountColumn::make('account.id')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->description(fn($record) => $record->created_at->diffForHumans())
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('uuid')
                    ->description(fn($record) => $record->type . ' by ' . $record->user?->name)
                    ->label('UUID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->sortable()
                    ->badge()
                    ->state(fn($record) => str($record->status)->ucfirst()->title()->toString())
                    ->color(fn($record) => match ($record->status) {
                        'pending' => 'warning',
                        'prepear' => 'info',
                        'withdrew' => 'danger',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'part-delivered' => 'warning',
                        'returned' => 'danger',
                        'canceled' => 'danger',
                        default => 'secondary',
                    })
                    ->icon(fn($record) => match ($record->status) {
                        'pending' => 'heroicon-o-clock',
                        'prepear' => 'heroicon-o-arrows-pointing-in',
                        'withdrew' => 'heroicon-o-arrows-right-left',
                        'shipped' => 'heroicon-o-truck',
                        'delivered' => 'heroicon-o-check-circle',
                        'part-delivered' => 'heroicon-o-chevron-up-down',
                        'returned' => 'heroicon-o-archive-box-x-mark',
                        'canceled' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-clock',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->description(fn($record) => $record->phone)
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')
                    ->description(fn($record) => $record->country->name . ', '. $record->city->name . ', '. $record->area->name . ', '. $record->flat)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('shipper.name')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('shipping')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money())
                    ->money()
                    ->color('warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vat')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money())
                    ->money()
                    ->color('warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money())
                    ->money()
                    ->color('danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money())
                    ->money()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_approved')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_closed')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('payment_method')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Tables\Grouping\Group::make('status')
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::Modal)
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(trans('filament-ecommerce::messages.orders.filters.status'))
                    ->searchable()
                    ->options([
                        'pending' => 'Pending',
                        'prepear' => 'Prepear',
                        'withdrew' => 'Withdrew',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'part-delivered' => 'Part Delivered',
                        'returned' => 'Returned',
                        'canceled' => 'Canceled',
                    ]),
                Tables\Filters\Filter::make('company')
                    ->label(trans('filament-ecommerce::messages.orders.filters.company'))
                    ->form([
                        Forms\Components\Select::make('company_id')
                            ->label(trans('filament-ecommerce::messages.orders.filters.company'))
                            ->searchable()
                            ->options(Company::query()->pluck('name', 'id')->toArray())
                            ->live(),
                        Forms\Components\Select::make('branch_id')
                            ->searchable()
                            ->options(fn(Forms\Get $get) => Branch::query()->where('company_id', $get('company_id'))->pluck('name', 'id')->toArray())
                            ->label(trans('filament-ecommerce::messages.orders.filters.branch_id')),
                    ])
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['company_id'], fn(Builder $query, $company_id) => $query->where('company_id', $company_id))
                        ->when($data['branch_id'], fn(Builder $query, $branch_id) => $query->where('branch_id', $branch_id))
                    )
                   ,
                Tables\Filters\Filter::make('location')
                    ->label(trans('filament-ecommerce::messages.orders.filters.location'))
                    ->form([
                        Forms\Components\Select::make('country_id')
                            ->label(trans('filament-ecommerce::messages.orders.filters.country_id'))
                            ->searchable()
                            ->options(Country::query()->pluck('name', 'id')->toArray())
                            ->live(),
                        Forms\Components\Select::make('city_id')
                            ->label(trans('filament-ecommerce::messages.orders.filters.city_id'))
                            ->searchable()
                            ->options(fn(Forms\Get $get) => City::where('country_id', $get('country_id'))->pluck('name', 'id')->toArray()),
                        Forms\Components\Select::make('area_id')
                            ->label(trans('filament-ecommerce::messages.orders.filters.area_id'))
                            ->searchable()
                            ->options(fn(Forms\Get $get) => \TomatoPHP\FilamentLocations\Models\Area::where('city_id', $get('city_id'))->pluck('name', 'id')->toArray()),
                    ])
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['country_id'], fn(Builder $query, $country_id) => $query->where('country_id', $country_id))
                        ->when($data['city_id'], fn(Builder $query, $city_id) => $query->where('city_id', $city_id))
                        ->when($data['area_id'], fn(Builder $query, $area_id) => $query->where('area_id', $area_id))
                    ),
                Tables\Filters\SelectFilter::make('account_id')
                    ->label(trans('filament-ecommerce::messages.orders.filters.account_id'))
                    ->searchable()
                    ->options(Account::pluck('name', 'id')->toArray()),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(trans('filament-ecommerce::messages.orders.filters.user_id'))
                    ->searchable()
                    ->options(User::pluck('name', 'id')->toArray()),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label(trans('filament-ecommerce::messages.orders.filters.payment_method'))
                    ->searchable()
                    ->options([
                        'cash' => 'Cash',
                        'credit' => 'Credit',
                        'wallet' => 'Wallet',
                    ]),
                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label(trans('filament-ecommerce::messages.orders.filters.is_approved')),
                Tables\Filters\TernaryFilter::make('is_closed')
                    ->label(trans('filament-ecommerce::messages.orders.filters.is_closed')),
            ])
            ->actions([
                Tables\Actions\Action::make('shipping')
                    ->hidden(fn($record) => $record->status === 'prepear' && $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('shipping_vendor_id')
                            ->searchable()
                            ->live()
                            ->options(ShippingVendor::pluck('name', 'id')->toArray())
                            ->required()
                            ->label('Shipper'),
                        Forms\Components\Select::make('shipper_id')
                            ->searchable()
                            ->options(fn(Forms\Get $get)=> Delivery::query()->where('shipping_vendor_id', $get('shipping_vendor_id'))->pluck('name', 'id')->toArray())
                            ->required()
                            ->label('Shipper'),
                    ])
                    ->fillForm(fn($record) => [
                        'shipping_vendor_id' => $record->shipping_vendor_id,
                        'shipper_id' => $record->shipper_id,
                    ])
                    ->action(function($record, array $data){
                        $shippingPrice = 0;
                        $getShippingVendorPrices = ShippingPrice::query()
                            ->where('shipping_vendor_id', $data['shipping_vendor_id'])
                            ->where('country_id', $record->country_id)
                            ->where('city_id', $record->city_id)
                            ->where('area_id', $record->area_id)
                            ->where('delivery_id', $data['shipper_id'])
                            ->orWhereNull('delivery_id')
                            ->first();

                        if($getShippingVendorPrices){
                            $shippingPrice = $getShippingVendorPrices->price;
                        }
                        else {
                            $shippingPrice = ShippingVendor::find($data['shipping_vendor_id'])?->price;
                        }

                        $record->update([
                            'shipping_vendor_id' => $data['shipping_vendor_id'],
                            'shipper_id' => $data['shipper_id'],
                            'status' => 'shipped',
                            'shipping' => $shippingPrice,
                            'total' => $record->ordersItems()->sum('total') + $shippingPrice,
                        ]);

                        $orderLog = new OrderLog();
                        $orderLog->user_id = auth()->user()->id;
                        $orderLog->order_id = $record->id;
                        $orderLog->status = $record->status;
                        $orderLog->is_closed = 1;
                        $orderLog->note = 'Order Shipper has been selected: '. $record->delivery?->name . ' by: '.auth()->user()->name. ' and Total: '.number_format($record->total, 2);
                        $orderLog->save();

                        Notification::make()
                            ->title('Order Shipper Changed')
                            ->body('Order Shipper has been selected: ' . $record->delivery?->name)
                            ->success()
                            ->send();
                    })
                    ->tooltip(trans('filament-ecommerce::messages.orders.actions.shipping'))
                    ->icon('heroicon-o-truck')
                    ->color('danger')
                    ->iconButton(),
                Tables\Actions\Action::make('status')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->searchable()
                            ->options([
                                'pending' => 'Pending',
                                'prepear' => 'Prepear',
                                'withdrew' => 'Withdrew',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'part-delivered' => 'Part Delivered',
                                'returned' => 'Returned',
                                'canceled' => 'Canceled',
                            ])
                            ->required()
                            ->default('pending'),
                    ])
                    ->fillForm(fn($record) => [
                        'status' => $record->status,
                    ])
                    ->action(function($record, array $data){
                        $record->update(['status' => $data['status']]);

                        $orderLog = new OrderLog();
                        $orderLog->user_id = auth()->user()->id;
                        $orderLog->order_id = $record->id;
                        $orderLog->status = $record->status;
                        $orderLog->is_closed = 1;
                        $orderLog->note = 'Order update by '.auth()->user()->name. ' and Total: '.number_format($record->total, 2);
                        $orderLog->save();

                        Notification::make()
                            ->title('Order Status Changed')
                            ->body('Order status has been changed to ' . $data['status'])
                            ->success()
                            ->send();
                    })
                    ->tooltip(trans('filament-ecommerce::messages.orders.actions.status'))
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->iconButton(),
                Tables\Actions\Action::make('print')
                    ->tooltip(trans('filament-ecommerce::messages.orders.actions.print'))
                    ->icon('heroicon-o-printer')
                    ->openUrlInNewTab()
                    ->url(fn($record) => route('order.print', $record->id))
                    ->iconButton(),
                Tables\Actions\ViewAction::make()
                    ->tooltip(trans('filament-ecommerce::messages.orders.actions.show'))
                    ->iconButton(),
                Tables\Actions\EditAction::make()
                    ->tooltip(trans('filament-ecommerce::messages.orders.actions.edit'))
                    ->iconButton(),
            ])
            ->defaultSort('created_at', 'desc')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrderLog::make()
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
            'view' => Pages\ViewOrder::route('/{record}/show'),
        ];
    }
}
