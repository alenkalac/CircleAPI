import java.util.ArrayList;
import java.util.Iterator;
import java.util.List;

public class ProcessRequests implements Runnable {

    private List<RequestTransaction> requests;

    public ProcessRequests(){
        System.out.println("STARTING PROCESS REQUESTS SERVICE  ");
        requests = new ArrayList<RequestTransaction>();
    }

    public void addRequest(RequestTransaction request) {
        requests.add(request);
    }

    private void sleep(int millis) {
        try {
            Thread.sleep(millis);
        } catch (InterruptedException e) {
            e.printStackTrace();
        }
    }

    public void run() {


        while(true) {
            for(Iterator<RequestTransaction> iterator = requests.iterator(); iterator.hasNext();) {
                RequestTransaction r = iterator.next();
                if(!r.isAlive())
                    r.start();
                iterator.remove();
            }
            this.sleep(5000);
        }
    }
}
