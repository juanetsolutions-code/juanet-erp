<?php

namespace App\Providers;

use App\Repositories\Eloquent\OrganizationRepository;
use App\Repositories\Eloquent\PermissionRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\OrganizationRepositoryInterface;
use App\Repositories\PermissionRepositoryInterface;
use App\Repositories\RoleRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use App\Services\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Repository interfaces to their Eloquent implementations
        $this->app->bind(OrganizationRepositoryInterface::class, OrganizationRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);

        // Bind Logging Repositories
        $this->app->bind(\App\Repositories\ActivityLogRepositoryInterface::class, \App\Repositories\Eloquent\ActivityLogRepository::class);
        $this->app->bind(\App\Repositories\AuditLogRepositoryInterface::class, \App\Repositories\Eloquent\AuditLogRepository::class);
        $this->app->bind(\App\Repositories\SecurityLogRepositoryInterface::class, \App\Repositories\Eloquent\SecurityLogRepository::class);
        $this->app->bind(\App\Repositories\ExceptionLogRepositoryInterface::class, \App\Repositories\Eloquent\ExceptionLogRepository::class);

        // Bind Logging Services
        $this->app->bind(\App\Services\ActivityLogServiceInterface::class, \App\Services\ActivityLogService::class);
        $this->app->bind(\App\Services\AuditLogServiceInterface::class, \App\Services\AuditLogService::class);
        $this->app->bind(\App\Services\SecurityLogServiceInterface::class, \App\Services\SecurityLogService::class);
        $this->app->bind(\App\Services\ExceptionLogServiceInterface::class, \App\Services\ExceptionLogService::class);

        // Bind Notification Repository and Service
        $this->app->bind(\App\Repositories\NotificationRepositoryInterface::class, \App\Repositories\Eloquent\NotificationRepository::class);
        $this->app->bind(\App\Services\NotificationServiceInterface::class, \App\Services\NotificationService::class);

        // Bind File Storage Repositories and Services
        $this->app->bind(\App\Repositories\FileRepositoryInterface::class, \App\Repositories\Eloquent\FileRepository::class);
        $this->app->bind(\App\Services\FileValidatorInterface::class, \App\Services\FileValidator::class);
        $this->app->bind(\App\Services\VirusScannerInterface::class, \App\Services\VirusScanner::class);
        $this->app->bind(\App\Services\ImageOptimizationServiceInterface::class, \App\Services\ImageOptimizationService::class);
        $this->app->bind(\App\Services\ThumbnailGeneratorInterface::class, \App\Services\ThumbnailGenerator::class);
        $this->app->bind(\App\Services\SignedUrlServiceInterface::class, \App\Services\SignedUrlService::class);
        $this->app->bind(\App\Services\UploadServiceInterface::class, \App\Services\UploadService::class);
        $this->app->bind(\App\Services\DownloadServiceInterface::class, \App\Services\DownloadService::class);

        // Bind Search Repositories and Services
        $this->app->bind(\App\Repositories\SearchRepositoryInterface::class, \App\Repositories\Eloquent\SearchRepository::class);
        $this->app->bind(\App\Services\SearchServiceInterface::class, \App\Services\SearchService::class);

        // Bind Event Bus and Transactional Outbox Services
        $this->app->singleton(\App\Services\EventBus\IdempotencyCheckerInterface::class, \App\Services\EventBus\IdempotencyChecker::class);
        $this->app->bind(\App\Services\EventBus\RetryServiceInterface::class, \App\Services\EventBus\RetryService::class);
        $this->app->bind(\App\Services\EventBus\DeadLetterQueueInterface::class, \App\Services\EventBus\DeadLetterQueue::class);
        $this->app->bind(\App\Services\EventBus\TransactionalOutboxInterface::class, \App\Services\EventBus\TransactionalOutbox::class);
        $this->app->singleton(\App\Services\EventBus\EventDispatcherInterface::class, \App\Services\EventBus\EventDispatcher::class);
        $this->app->bind(\App\Services\EventBus\OutboxConsumerInterface::class, \App\Services\EventBus\OutboxConsumer::class);
        $this->app->bind(\App\Services\EventBus\OutboxPublisherInterface::class, \App\Services\EventBus\OutboxPublisher::class);
        $this->app->singleton(\App\Contracts\EventBus::class, \App\Infrastructure\Events\LaravelEventBus::class);

        // Bind Enterprise Cache Infrastructure Services
        $this->app->singleton(\App\Services\Cache\CacheServiceInterface::class, \App\Services\Cache\CacheService::class);
        $this->app->singleton(\App\Services\Cache\TenantCacheManagerInterface::class, \App\Services\Cache\TenantCacheManager::class);
        $this->app->singleton(\App\Services\Cache\RedisRepositoryInterface::class, \App\Services\Cache\RedisRepository::class);
        $this->app->singleton(\App\Services\Cache\CacheInvalidator::class);

        // Bind Configuration & Feature Flag Repositories and Services
        $this->app->singleton(\App\Repositories\SettingsRepositoryInterface::class, \App\Repositories\Eloquent\SettingsRepository::class);
        $this->app->singleton(\App\Services\Configuration\ConfigurationServiceInterface::class, \App\Services\Configuration\ConfigurationService::class);

        // Bind TenantContext as a singleton
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext();
        });

        // Bind CRM Repositories
        $this->app->bind(\App\Domain\CRM\Contracts\LeadRepositoryInterface::class, \App\Domain\CRM\Repositories\LeadRepository::class);
        $this->app->bind(\App\Domain\CRM\Contracts\ContactRepositoryInterface::class, \App\Domain\CRM\Repositories\ContactRepository::class);
        $this->app->bind(\App\Domain\CRM\Contracts\CompanyRepositoryInterface::class, \App\Domain\CRM\Repositories\CompanyRepository::class);
        $this->app->bind(\App\Domain\CRM\Contracts\PipelineRepositoryInterface::class, \App\Domain\CRM\Repositories\PipelineRepository::class);
        $this->app->bind(\App\Domain\CRM\Contracts\OpportunityRepositoryInterface::class, \App\Domain\CRM\Repositories\OpportunityRepository::class);

        // Bind Marketplace Repositories
        $this->app->bind(\App\Domain\Marketplace\Contracts\MarketplaceProductRepositoryInterface::class, \App\Domain\Marketplace\Repositories\MarketplaceProductRepository::class);
        $this->app->bind(\App\Domain\Marketplace\Contracts\MarketplaceCategoryRepositoryInterface::class, \App\Domain\Marketplace\Repositories\MarketplaceCategoryRepository::class);

        // Bind Shared Kernel Money Value Object Formatter
        $this->app->bind(\App\Domain\Shared\Contracts\MoneyFormatter::class, \App\Domain\Shared\Services\LocaleMoneyFormatter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register wildcards for Eloquent model auditing
        \Illuminate\Support\Facades\Event::listen('eloquent.created: *', [\App\Listeners\EloquentAuditListener::class, 'handle']);
        \Illuminate\Support\Facades\Event::listen('eloquent.updated: *', [\App\Listeners\EloquentAuditListener::class, 'handle']);
        \Illuminate\Support\Facades\Event::listen('eloquent.deleted: *', [\App\Listeners\EloquentAuditListener::class, 'handle']);

        // Register Auth events subscriber
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\AuthEventListener::class);

        // Register CRM events subscriber
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\CrmDomainEventSubscriber::class);

        // Register Model Observers
        \App\Models\StoredFile::observe(\App\Observers\StoredFileObserver::class);

        // Register CRM Model Observers
        \App\Domain\CRM\Models\Lead::observe(\App\Domain\CRM\Observers\LeadObserver::class);
        \App\Domain\CRM\Models\Contact::observe(\App\Domain\CRM\Observers\ContactObserver::class);
        \App\Domain\CRM\Models\Company::observe(\App\Domain\CRM\Observers\CompanyObserver::class);
        \App\Domain\CRM\Models\Opportunity::observe(\App\Domain\CRM\Observers\OpportunityObserver::class);
        \App\Domain\CRM\Models\Pipeline::observe(\App\Domain\CRM\Observers\PipelineObserver::class);

        // Register Gate policies
        \Illuminate\Support\Facades\Gate::policy(\App\Domain\CRM\Models\Lead::class, \App\Domain\CRM\Policies\LeadPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Domain\CRM\Models\Contact::class, \App\Domain\CRM\Policies\ContactPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Domain\CRM\Models\Company::class, \App\Domain\CRM\Policies\CompanyPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Domain\CRM\Models\Opportunity::class, \App\Domain\CRM\Policies\OpportunityPolicy::class);
    }
}
