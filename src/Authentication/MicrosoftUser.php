<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Authentication;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

/**
 * Represents a Microsoft user account.
 */
final class MicrosoftUser implements ResourceOwnerInterface
{
    private array $userData;

    public function __construct(array $userData)
    {
        $this->userData = $userData;
    }

    /**
     * Get the user's ID.
     */
    public function getId(): string
    {
        return $this->userData['id'] ?? '';
    }

    /**
     * Get the user's display name.
     */
    public function getDisplayName(): string
    {
        return $this->userData['displayName'] ?? '';
    }

    /**
     * Get the user's first name.
     */
    public function getFirstName(): string
    {
        return $this->userData['givenName'] ?? '';
    }

    /**
     * Get the user's last name.
     */
    public function getLastName(): string
    {
        return $this->userData['lastName'] ?? '';
    }

    /**
     * Get the user's email address.
     */
    public function getEmail(): string
    {
        return $this->userData['mail'] ?? $this->userData['userPrincipalName'] ?? '';
    }

    /**
     * Get the user's principal name (UPN).
     */
    public function getUserPrincipalName(): string
    {
        return $this->userData['userPrincipalName'] ?? '';
    }

    /**
     * Get the user's job title.
     */
    public function getJobTitle(): string
    {
        return $this->userData['jobTitle'] ?? '';
    }

    /**
     * Get the user's department.
     */
    public function getDepartment(): string
    {
        return $this->userData['department'] ?? '';
    }

    /**
     * Get the user's office location.
     */
    public function getOfficeLocation(): string
    {
        return $this->userData['officeLocation'] ?? '';
    }

    /**
     * Get the user's mobile phone number.
     */
    public function getMobilePhone(): string
    {
        return $this->userData['mobilePhone'] ?? '';
    }

    /**
     * Get the user's business phone number.
     */
    public function getBusinessPhone(): string
    {
        return $this->userData['businessPhones'][0] ?? '';
    }

    /**
     * Get the user's preferred language.
     */
    public function getPreferredLanguage(): string
    {
        return $this->userData['preferredLanguage'] ?? '';
    }

    /**
     * Get the user's country.
     */
    public function getCountry(): string
    {
        return $this->userData['country'] ?? '';
    }

    /**
     * Get the user's city.
     */
    public function getCity(): string
    {
        return $this->userData['city'] ?? '';
    }

    /**
     * Get the user's state.
     */
    public function getState(): string
    {
        return $this->userData['state'] ?? '';
    }

    /**
     * Get the user's postal code.
     */
    public function getPostalCode(): string
    {
        return $this->userData['postalCode'] ?? '';
    }

    /**
     * Get the user's street address.
     */
    public function getStreetAddress(): string
    {
        return $this->userData['streetAddress'] ?? '';
    }

    /**
     * Get the user's company name.
     */
    public function getCompanyName(): string
    {
        return $this->userData['companyName'] ?? '';
    }

    /**
     * Get the user's employee ID.
     */
    public function getEmployeeId(): string
    {
        return $this->userData['employeeId'] ?? '';
    }

    /**
     * Get the user's manager information.
     */
    public function getManager(): ?array
    {
        return $this->userData['manager'] ?? null;
    }

    /**
     * Get the user's direct reports.
     */
    public function getDirectReports(): array
    {
        return $this->userData['directReports'] ?? [];
    }

    /**
     * Get the user's photo URL.
     */
    public function getPhotoUrl(): string
    {
        return $this->userData['photo'] ?? '';
    }

    /**
     * Check if the user has a photo.
     */
    public function hasPhoto(): bool
    {
        return !empty($this->getPhotoUrl());
    }

    /**
     * Get the user's account enabled status.
     */
    public function isAccountEnabled(): bool
    {
        return ($this->userData['accountEnabled'] ?? true) === true;
    }

    /**
     * Get the user's usage location.
     */
    public function getUsageLocation(): string
    {
        return $this->userData['usageLocation'] ?? '';
    }

    /**
     * Get the user's hire date.
     */
    public function getHireDate(): ?string
    {
        return $this->userData['hireDate'] ?? null;
    }

    /**
     * Get the user's employee hire date.
     */
    public function getEmployeeHireDate(): ?string
    {
        return $this->userData['employeeHireDate'] ?? null;
    }

    /**
     * Get the user's employee leave date.
     */
    public function getEmployeeLeaveDate(): ?string
    {
        return $this->userData['employeeLeaveDateTime'] ?? null;
    }

    /**
     * Get the user's external user state.
     */
    public function getExternalUserState(): ?string
    {
        return $this->userData['externalUserState'] ?? null;
    }

    /**
     * Get the user's external user state change date.
     */
    public function getExternalUserStateChangeDate(): ?string
    {
        return $this->userData['externalUserStateChangeDateTime'] ?? null;
    }

    /**
     * Get the user's fax number.
     */
    public function getFaxNumber(): string
    {
        return $this->userData['faxNumber'] ?? '';
    }

    /**
     * Get the user's age group.
     */
    public function getAgeGroup(): string
    {
        return $this->userData['ageGroup'] ?? '';
    }

    /**
     * Get the user's consent provided for minor.
     */
    public function getConsentProvidedForMinor(): ?string
    {
        return $this->userData['consentProvidedForMinor'] ?? null;
    }

    /**
     * Get the user's legal age group classification.
     */
    public function getLegalAgeGroupClassification(): ?string
    {
        return $this->userData['legalAgeGroupClassification'] ?? null;
    }

    /**
     * Get the user's creation type.
     */
    public function getCreationType(): ?string
    {
        return $this->userData['creationType'] ?? null;
    }

    /**
     * Get the user's immutable ID.
     */
    public function getImmutableId(): ?string
    {
        return $this->userData['immutableId'] ?? null;
    }

    /**
     * Get the user's on-premises domain name.
     */
    public function getOnPremisesDomainName(): ?string
    {
        return $this->userData['onPremisesDomainName'] ?? null;
    }

    /**
     * Get the user's on-premises SAM account name.
     */
    public function getOnPremisesSamAccountName(): ?string
    {
        return $this->userData['onPremisesSamAccountName'] ?? null;
    }

    /**
     * Get the user's on-premises security identifier.
     */
    public function getOnPremisesSecurityIdentifier(): ?string
    {
        return $this->userData['onPremisesSecurityIdentifier'] ?? null;
    }

    /**
     * Get the user's on-premises sync enabled status.
     */
    public function isOnPremisesSyncEnabled(): ?bool
    {
        return $this->userData['onPremisesSyncEnabled'] ?? null;
    }

    /**
     * Get the user's on-premises last sync date.
     */
    public function getOnPremisesLastSyncDate(): ?string
    {
        return $this->userData['onPremisesLastSyncDateTime'] ?? null;
    }

    /**
     * Get the user's on-premises user principal name.
     */
    public function getOnPremisesUserPrincipalName(): ?string
    {
        return $this->userData['onPremisesUserPrincipalName'] ?? null;
    }

    /**
     * Get the user's proxy addresses.
     */
    public function getProxyAddresses(): array
    {
        return $this->userData['proxyAddresses'] ?? [];
    }

    /**
     * Get the user's other email addresses.
     */
    public function getOtherMails(): array
    {
        return $this->userData['otherMails'] ?? [];
    }

    /**
     * Get the user's identities.
     */
    public function getIdentities(): array
    {
        return $this->userData['identities'] ?? [];
    }

    /**
     * Get the user's sign-in sessions valid from date.
     */
    public function getSignInSessionsValidFromDate(): ?string
    {
        return $this->userData['signInSessionsValidFromDateTime'] ?? null;
    }

    /**
     * Get the user's show in address list status.
     */
    public function getShowInAddressList(): ?bool
    {
        return $this->userData['showInAddressList'] ?? null;
    }

    /**
     * Get the user's created date.
     */
    public function getCreatedDate(): ?string
    {
        return $this->userData['createdDateTime'] ?? null;
    }

    /**
     * Get the user's last password change date.
     */
    public function getLastPasswordChangeDate(): ?string
    {
        return $this->userData['lastPasswordChangeDateTime'] ?? null;
    }

    /**
     * Get the user's preferred data location.
     */
    public function getPreferredDataLocation(): ?string
    {
        return $this->userData['preferredDataLocation'] ?? null;
    }

    /**
     * Get the user's mailbox settings.
     */
    public function getMailboxSettings(): array
    {
        return $this->userData['mailboxSettings'] ?? [];
    }

    /**
     * Get the user's about me description.
     */
    public function getAboutMe(): string
    {
        return $this->userData['aboutMe'] ?? '';
    }

    /**
     * Get the user's birthday.
     */
    public function getBirthday(): ?string
    {
        return $this->userData['birthday'] ?? null;
    }

    /**
     * Get the user's hire date.
     */
    public function getHireDateTime(): ?string
    {
        return $this->userData['hireDate'] ?? null;
    }

    /**
     * Get the user's interests.
     */
    public function getInterests(): array
    {
        return $this->userData['interests'] ?? [];
    }

    /**
     * Get the user's past projects.
     */
    public function getPastProjects(): array
    {
        return $this->userData['pastProjects'] ?? [];
    }

    /**
     * Get the user's preferred name.
     */
    public function getPreferredName(): string
    {
        return $this->userData['preferredName'] ?? '';
    }

    /**
     * Get the user's responsibilities.
     */
    public function getResponsibilities(): array
    {
        return $this->userData['responsibilities'] ?? [];
    }

    /**
     * Get the user's schools.
     */
    public function getSchools(): array
    {
        return $this->userData['schools'] ?? [];
    }

    /**
     * Get the user's skills.
     */
    public function getSkills(): array
    {
        return $this->userData['skills'] ?? [];
    }

    /**
     * Get the user's license details.
     */
    public function getLicenseDetails(): array
    {
        return $this->userData['licenseDetails'] ?? [];
    }

    /**
     * Get the user's provisioned plans.
     */
    public function getProvisionedPlans(): array
    {
        return $this->userData['provisionedPlans'] ?? [];
    }

    /**
     * Get the user's refresh tokens valid from date.
     */
    public function getRefreshTokensValidFromDate(): ?string
    {
        return $this->userData['refreshTokensValidFromDateTime'] ?? null;
    }

    /**
     * Get the user's security identifier.
     */
    public function getSecurityIdentifier(): ?string
    {
        return $this->userData['securityIdentifier'] ?? null;
    }

    /**
     * Get the user's surname.
     */
    public function getSurname(): string
    {
        return $this->userData['surname'] ?? '';
    }

    /**
     * Get the user's user type.
     */
    public function getUserType(): string
    {
        return $this->userData['userType'] ?? '';
    }

    /**
     * Get the raw user data.
     */
    public function getRawData(): array
    {
        return $this->userData;
    }

    /**
     * Convert user to array.
     */
    public function toArray(): array
    {
        return $this->userData;
    }

    /**
     * Convert user to JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get a specific field from user data.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->userData[$key] ?? $default;
    }

    /**
     * Check if a field exists in user data.
     */
    public function has(string $key): bool
    {
        return isset($this->userData[$key]);
    }

    /**
     * String representation of the user.
     */
    public function __toString(): string
    {
        return $this->getDisplayName() ?: $this->getEmail() ?: $this->getId();
    }

    /**
     * Get the resource owner ID (required by interface).
     */
    public function getResourceOwnerId(): string
    {
        return $this->getId();
    }
}
