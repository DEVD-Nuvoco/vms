Objective

To develop an application for Early Out and Late IN tracking for Workmen.
To integrate this within the Contractor Management System for a paperless process.


Key Requirements
1. Application Overview
    Application to support:
        Early Out and Late IN tracking
        Contractor & vendor management

    UI to be developed with reference to the Visitor Management System.

2. Gate Pass Management
    Gate pass creation required for:
        Insider Vendors
        Outsider Vendors

3. Common Validations (Applicable to Both)
    Gate pass validity: 6 months
    Post expiry:
        Gate pass must be recreated following the same process

    Process flow:
        Defined in a Word document (shared during the meeting)
        Pending to be shared with the team

4. Insider Vendor Pass Creation

    Required:
        Supporting documents

    Detailed process:
        As per Word document (to be shared)

5. Outsider Vendor Pass Creation
    Approval requirement:
        N + 1 level approval mandatory

6. Application Features
    Admin Panel
    Role-based access control




Time Office creates application (Late IN / Early Out + reason)
Approval: Supervisor -> N-1 -> HOD
After approval → Time Office attests → Security executes gate IN/OUT



make HOD deprtment wise
multiple role can be assigned to one user


N-1 will create the Application





HR (HOD/Head)

eg, Plant A -> department wise single HOD and multiple N-1

Admin
    "HR (HOD/Head)" department wise must create to begin with anything
    Matser data creation, modification, deletion 
    Approval goes "HR (HOD/Head)" once apporve then action should perform

Time Office and Security
    Only have view mode
    can see and download the excel report by seelecting the date range


HOD cannot be N-1


Role assigned by time office user multiple user
  
Creation rights : Admin
                    1. RCP (Plant Name)
                    2. Multiple department
                    3. Individual department HOD
                    4. Multiple N-1
 
Admin Rights :  Master data creation
                Data Modification
                Data deletion

HR (HOD/Head) : Approval rights 
 
 
Time Office & Security Office should have view-only access. They should be able to view records for the last 6 months and have the option to export data in both Excel and PDF formats.
Security office : Remark input option after final approval.
Supervisor:- View options only for approvals.
 

HOD : If an HOD exits the organization, the HOD role should be temporarily assigned to another department HOD. The approval/rejection authority for this assignment should be routed to the "HR (HOD/Head)"



Make changes only for LIEO Application C:\wamp64\www\vms\lieo only

I have to manualy add the users details so for this
    - In Admin Side give me like link like /lieo/login.php?add=manually
    - give me bulk upload functionality by uploading by plant
    - User have given the deatail of user check the C:\wamp64\www\vms\resources\HOD & N+1.xlsx
    - When i add this give resend btn for to send the login credential by mail


Approval flow is like :-
N-1 Create the Application
HOD Approve
Securtiy close the application by remark


Roles are : 

Admin ->
    - He have rights to create the HOD, N-1 and Security role but approval required of HR department HOD
    - Able to assign the same HOD for two department, but other department should not there eg ABC name user is Sales HOD & Machanical dont have HOD, the Admin can assign the ABC user for Sales & Machanical HOD.

Time Office ->
    - Should be from HR Department person only and they should be plant specific only.
    - Track the Application

HOD -> 
    - Per department single HOD.
    - HR department HOD should have access to see the all users of that plant.
    - Some case one user should have the two department HOD, on that case show different Department wise Approval Interface.
    - HR depertment HOD should have two additional sidebar tabs one is "User Approval" here admin user create, delete or update request will come and "Application Approval" here LIEO Application approval come 

N-1 -> This role should multiple per department
    - Create the LIEO Application.
    - If HOD not found of that department dont let create the application and give error msg like eg "Sales department HOD is not created please contact to Admin"
    - Track the Application of his department only

Security ->  
    -> Should be from HR Department person only and they should be plant specific only.
    - Track the Application of his department only


Have functionality like
    If replacing the HOD of same department then current HOD record should tranfered to new one the exiting data and approval request of that department should not erased it should be tranfered to new one.


Time Office and Security can be only one from HR Department
