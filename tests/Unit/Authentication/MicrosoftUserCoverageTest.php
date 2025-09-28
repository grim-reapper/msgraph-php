<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Tests\Unit\Authentication;

use GrimReapper\MsGraph\Authentication\MicrosoftUser;
use PHPUnit\Framework\TestCase;

class MicrosoftUserCoverageTest extends TestCase
{
    public function testAllGetters(): void
    {
        $userData = [
            'id' => '12345',
            'displayName' => 'John Doe',
            'givenName' => 'John',
            'lastName' => 'Doe',
            'surname' => 'Doe',
            'mail' => 'john.doe@contoso.com',
            'userPrincipalName' => 'johndoe@example.com',
            'jobTitle' => 'Developer',
            'department' => 'IT',
            'officeLocation' => 'Building 1',
            'mobilePhone' => '555-0100',
            'businessPhones' => ['555-0101'],
            'preferredLanguage' => 'en-US',
            'country' => 'USA',
            'city' => 'Redmond',
            'state' => 'WA',
            'postalCode' => '98052',
            'streetAddress' => '1 Microsoft Way',
            'companyName' => 'Microsoft',
            'employeeId' => 'E123',
            'manager' => ['id' => 'manager123'],
            'directReports' => [['id' => 'reportee123']],
            'photo' => 'photourl',
            'accountEnabled' => true,
            'usageLocation' => 'US',
            'hireDate' => '2020-01-01T00:00:00Z',
            'employeeHireDate' => '2020-01-01T00:00:00Z',
            'employeeLeaveDateTime' => '2025-01-01T00:00:00Z',
            'externalUserState' => 'Member',
            'externalUserStateChangeDateTime' => '2020-01-01T00:00:00Z',
            'faxNumber' => '555-0102',
            'ageGroup' => 'adult',
            'consentProvidedForMinor' => 'granted',
            'legalAgeGroupClassification' => 'adult',
            'creationType' => 'LocalAccount',
            'immutableId' => 'immutable123',
            'onPremisesDomainName' => 'contoso.com',
            'onPremisesSamAccountName' => 'johndoe',
            'onPremisesSecurityIdentifier' => 'sid123',
            'onPremisesSyncEnabled' => true,
            'onPremisesLastSyncDateTime' => '2023-01-01T00:00:00Z',
            'onPremisesUserPrincipalName' => 'johndoe@contoso.com',
            'proxyAddresses' => ['SMTP:johndoe@contoso.com'],
            'otherMails' => ['johndoe@personal.com'],
            'identities' => [['signInType' => 'emailAddress']],
            'signInSessionsValidFromDateTime' => '2020-01-01T00:00:00Z',
            'showInAddressList' => true,
            'createdDateTime' => '2019-01-01T00:00:00Z',
            'lastPasswordChangeDateTime' => '2024-01-01T00:00:00Z',
            'preferredDataLocation' => 'US',
            'mailboxSettings' => ['automaticRepliesSetting' => ['status' => 'disabled']],
            'aboutMe' => 'About John Doe',
            'birthday' => '2000-01-01',
            'interests' => ['coding', 'hiking'],
            'pastProjects' => ['Project X'],
            'preferredName' => 'Johnny',
            'responsibilities' => ['Develop stuff'],
            'schools' => ['University of Testing'],
            'skills' => ['PHP', 'Testing'],
            'licenseDetails' => [['skuId' => 'sku123']],
            'provisionedPlans' => [['capabilityStatus' => 'Enabled']],
            'refreshTokensValidFromDateTime' => '2024-01-01T00:00:00Z',
            'securityIdentifier' => 'secid123',
            'userType' => 'Member',
        ];

        $user = new MicrosoftUser($userData);

        $this->assertSame($userData['id'], $user->getId());
        $this->assertSame($userData['displayName'], $user->getDisplayName());
        $this->assertSame($userData['givenName'], $user->getFirstName());
        $this->assertSame($userData['lastName'], $user->getLastName());
        $this->assertSame($userData['surname'], $user->getSurname());
        $this->assertSame($userData['mail'], $user->getEmail());
        $this->assertSame($userData['userPrincipalName'], $user->getUserPrincipalName());
        $this->assertSame($userData['jobTitle'], $user->getJobTitle());
        $this->assertSame($userData['department'], $user->getDepartment());
        $this->assertSame($userData['officeLocation'], $user->getOfficeLocation());
        $this->assertSame($userData['mobilePhone'], $user->getMobilePhone());
        $this->assertSame($userData['businessPhones'][0], $user->getBusinessPhone());
        $this->assertSame($userData['preferredLanguage'], $user->getPreferredLanguage());
        $this->assertSame($userData['country'], $user->getCountry());
        $this->assertSame($userData['city'], $user->getCity());
        $this->assertSame($userData['state'], $user->getState());
        $this->assertSame($userData['postalCode'], $user->getPostalCode());
        $this->assertSame($userData['streetAddress'], $user->getStreetAddress());
        $this->assertSame($userData['companyName'], $user->getCompanyName());
        $this->assertSame($userData['employeeId'], $user->getEmployeeId());
        $this->assertSame($userData['manager'], $user->getManager());
        $this->assertSame($userData['directReports'], $user->getDirectReports());
        $this->assertSame($userData['photo'], $user->getPhotoUrl());
        $this->assertTrue($user->hasPhoto());
        $this->assertTrue($user->isAccountEnabled());
        $this->assertSame($userData['usageLocation'], $user->getUsageLocation());
        $this->assertSame($userData['hireDate'], $user->getHireDate());
        $this->assertSame($userData['employeeHireDate'], $user->getEmployeeHireDate());
        $this->assertSame($userData['employeeLeaveDateTime'], $user->getEmployeeLeaveDate());
        $this->assertSame($userData['externalUserState'], $user->getExternalUserState());
        $this->assertSame($userData['externalUserStateChangeDateTime'], $user->getExternalUserStateChangeDate());
        $this->assertSame($userData['faxNumber'], $user->getFaxNumber());
        $this->assertSame($userData['ageGroup'], $user->getAgeGroup());
        $this->assertSame($userData['consentProvidedForMinor'], $user->getConsentProvidedForMinor());
        $this->assertSame($userData['legalAgeGroupClassification'], $user->getLegalAgeGroupClassification());
        $this->assertSame($userData['creationType'], $user->getCreationType());
        $this->assertSame($userData['immutableId'], $user->getImmutableId());
        $this->assertSame($userData['onPremisesDomainName'], $user->getOnPremisesDomainName());
        $this->assertSame($userData['onPremisesSamAccountName'], $user->getOnPremisesSamAccountName());
        $this->assertSame($userData['onPremisesSecurityIdentifier'], $user->getOnPremisesSecurityIdentifier());
        $this->assertTrue($user->isOnPremisesSyncEnabled());
        $this->assertSame($userData['onPremisesLastSyncDateTime'], $user->getOnPremisesLastSyncDate());
        $this->assertSame($userData['onPremisesUserPrincipalName'], $user->getOnPremisesUserPrincipalName());
        $this->assertSame($userData['proxyAddresses'], $user->getProxyAddresses());
        $this->assertSame($userData['otherMails'], $user->getOtherMails());
        $this->assertSame($userData['identities'], $user->getIdentities());
        $this->assertSame($userData['signInSessionsValidFromDateTime'], $user->getSignInSessionsValidFromDate());
        $this->assertTrue($user->getShowInAddressList());
        $this->assertSame($userData['createdDateTime'], $user->getCreatedDate());
        $this->assertSame($userData['lastPasswordChangeDateTime'], $user->getLastPasswordChangeDate());
        $this->assertSame($userData['preferredDataLocation'], $user->getPreferredDataLocation());
        $this->assertSame($userData['mailboxSettings'], $user->getMailboxSettings());
        $this->assertSame($userData['aboutMe'], $user->getAboutMe());
        $this->assertSame($userData['birthday'], $user->getBirthday());
        $this->assertSame($userData['hireDate'], $user->getHireDateTime());
        $this->assertSame($userData['interests'], $user->getInterests());
        $this->assertSame($userData['pastProjects'], $user->getPastProjects());
        $this->assertSame($userData['preferredName'], $user->getPreferredName());
        $this->assertSame($userData['responsibilities'], $user->getResponsibilities());
        $this->assertSame($userData['schools'], $user->getSchools());
        $this->assertSame($userData['skills'], $user->getSkills());
        $this->assertSame($userData['licenseDetails'], $user->getLicenseDetails());
        $this->assertSame($userData['provisionedPlans'], $user->getProvisionedPlans());
        $this->assertSame($userData['refreshTokensValidFromDateTime'], $user->getRefreshTokensValidFromDate());
        $this->assertSame($userData['securityIdentifier'], $user->getSecurityIdentifier());
        $this->assertSame($userData['userType'], $user->getUserType());
    }
}
