<?php
class Service {
    private $service_id;
    private $title;
    private $category;
    private $subcategory;
    private $price;
    private $delivery_time;
    private $revisions_included; // عدد التعديلات
    private $freelancer_id;
    private $freelancer_name;
    private $image;
    private $added_timestamp;
    
 
    //  array $data : Service data from database
    public function __construct($data) {
        $this->service_id = $data['service_id'];
        $this->title = $data['title'];
        $this->category = $data['category'] ?? '';
        $this->subcategory = $data['subcategory'] ?? '';
        $this->price = (float) $data['price'];
        $this->delivery_time = (int) $data['delivery_time'];
        $this->revisions_included = (int) $data['revisions_included'];
        $this->freelancer_id = $data['freelancer_id'];
        $this->freelancer_name = $data['freelancer_name'] ?? '';
        $this->image = $data['image'] ?? $data['image_1'] ?? '';
        $this->added_timestamp = time();
    }
    
    public function getServiceId() {
        return $this->service_id;
    }
    
    public function getTitle() {
        return $this->title;
    }
    
    public function getCategory() {
        return $this->category;
    }
    
    public function getSubcategory() {
        return $this->subcategory;
    }
    
    public function getPrice() {
        return $this->price;
    }
    
    public function getDeliveryTime() {
        return $this->delivery_time;
    }
    
    public function getRevisionsIncluded() {
        return $this->revisions_included;
    }
    
    public function getFreelancerId() {
        return $this->freelancer_id;
    }
    
    public function getFreelancerName() {
        return $this->freelancer_name;
    }
    
    public function getImage() {
        return $this->image;
    }
    
    public function getAddedTimestamp() {
        return $this->added_timestamp;
    }
    
    public function getFormattedPrice() {
        return '$' . number_format($this->price, 2);
    }
    
    public function getFormattedDelivery() {
        return $this->delivery_time . ' day' . ($this->delivery_time !== 1 ? 's' : '');
    }
    
 
    public function calculateServiceFee() {
        return $this->price * 0.05;
    }
    
   
    //   Get total price including service fee
    //   @return float Total amount (price + service fee)   
    public function getTotalWithFee() {
        return $this->price + $this->calculateServiceFee();
    }
    
    public function getFormattedTotal() {
        return '$' . number_format($this->getTotalWithFee(), 2);
    }
    
    /**
     * Get formatted revisions text
     * @return string Revisions text (e.g., "3 revisions" or "Unlimited")
     */
    public function getFormattedRevisions() {
        if ($this->revisions_included === 999) {
            return 'Unlimited';
        }
        return $this->revisions_included . ' revision' . 
               ($this->revisions_included !== 1 ? 's' : '');
    }
}
?>