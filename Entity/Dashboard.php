<?php

namespace Oro\Bundle\DashboardBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;

/**
 * Dashboard
 *
 * @ORM\Entity
 * @ORM\Table(name="oro_dashboard")
 * @Config(
 *  defaultValues={
 *      "ownership"={
 *          "owner_type"="USER",
 *          "owner_field_name"="owner",
 *          "owner_column_name"="user_owner_id"
 *      },
 *      "security"={
 *          "type"="ACL",a
 *          "group_name"=""
 *      }
 *  }
 * )
 */
class Dashboard
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255)
     */
    protected $label;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\UserBundle\Entity\User")
     * @ORM\JoinColumn(name="user_owner_id", referencedColumnName="id", onDelete="SET NULL")
     */
    protected $owner;

    /**
     * @var Collection
     *
     * @ORM\OneToMany(
     *     targetEntity="Oro\Bundle\DashboardBundle\Entity\DashboardWidget",
     *     mappedBy="dashboard", cascade={"ALL"}, orphanRemoval=true
     * )
     * @ORM\OrderBy({"position" = "ASC"})
     */
    protected $widgets;

    public function __construct()
    {
        $this->widgets = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $label
     * @return Dashboard
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $name
     * @return Dashboard
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param User $owner
     * @return Dashboard
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @return Collection
     */
    public function getWidgets()
    {
        return $this->widgets;
    }

    /**
     * @return $this
     */
    public function resetWidgets()
    {
        $this->getWidgets()->clear();

        return $this;
    }

    /**
     * @param DashboardWidget $widget
     * @return $this
     * @return Dashboard
     */
    public function addWidget(DashboardWidget $widget)
    {
        if (!$this->getWidgets()->contains($widget)) {
            $this->getWidgets()->add($widget);
            $widget->setDashboard($this);
        }

        return $this;
    }

    /**
     * @param DashboardWidget $widget
     * @return boolean
     */
    public function removeWidget(DashboardWidget $widget)
    {
        return $this->getWidgets()->removeElement($widget);
    }

    /**
     * @param DashboardWidget $widget
     * @return boolean
     */
    public function hasWidget(DashboardWidget $widget)
    {
        return $this->getWidgets()->contains($widget);
    }
}
