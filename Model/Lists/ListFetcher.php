<?php
declare(strict_types=1);

namespace Dotdigitalgroup\Email\Model\Lists;

use Dotdigitalgroup\Email\Model\Apiconnector\Client;
use Magento\Framework\Exception\LocalizedException;

class ListFetcher
{
    /**
     * @var array|\stdClass
     */
    private $lists = [];

    /**
     * Fetch all lists.
     *
     * @param Client $client
     * @return array
     * @throws LocalizedException
     */
    public function fetchAllLists(Client $client)
    {
        if (empty($this->lists)) {
            do {
                if (!is_array($listsResponse = $client->getAddressBooks(count($this->lists)))) {
                    return (array) $listsResponse;
                }
                $this->lists = array_merge($this->lists, $listsResponse);
            } while (count($listsResponse) === 1000);
        }

        return $this->lists;
    }
}
