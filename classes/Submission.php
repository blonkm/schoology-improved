<?
class Submission {
  public $grade;
  public $comment;
  public $member;
  public $userId;
  public $group;
  public $first_name;
  public $last_name;
  public $revision;
  public $size;
  public $apiUrl;
  public $url;
  public $name;
  public $datetime;
  public $saveAs;
  public $assignmentId;
  public $course;
  public $sectionId;
  public $id;
  
  public function setCourse($course) {
    $this->course = $course;
    return $this;
  }
  
  public function setSection($id) {
    $this->sectionId = $id;
    return $this;
  }

  public function setFile($file) {
    $this->file = $file;
    $this->apiUrl = (new Filesystem)->removeBase(Course::API_BASE, $file->download_path);   
    $this->url = "?section=" . $this->sectionId . "&submission=" . $this->id . "&action=file";
    $this->name = $file->title;
    $this->datetime = date(Course::DATETIME_FORMAT, $file->timestamp);
    return $this;
  }

  public function setGroup($group) {
    $this->group = $group;
    return $this;
  }

  public function setAssignment($assignment) {
    $this->assignmentId = $assignment;    
    return $this;
  }

  public function setMember($member) {
    $this->member = $member;
    $this->userId = formatId($member->school_uid);
    $this->first_name = $member->name_first;
    $this->last_name = $member->name_last;
    return $this;
  }

  public function setRevision($revision) {
    $this->revision = $revision->revision_id;
    $MB = 1024*1024;
    $this->size = round($revision->attachments->files->file[0]->filesize / $MB, 1);
    return $this;
  }

  public function setFileName() {
    $fileNameParts = [$this->last_name, $this->first_name, $this->userId, $this->revision, $this->name];
    $fileName = join(' ', $fileNameParts);
    $sanitized = (new Filesystem)->sanitize($fileName);
    $this->saveAs = $sanitized;
    return $this;
  }  
  
  public function setId($id) {
    $this->id = $id;
    return $this;
  }
}
?>